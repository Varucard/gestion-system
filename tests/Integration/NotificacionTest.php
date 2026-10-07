<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\TurnoRepository;
use App\Services\NotificacionService;
use App\Services\TurnoService;
use DateTimeImmutable;
use Tests\Support\CanalDePrueba;

final class NotificacionTest extends IntegrationTestCase
{
  private CanalDePrueba $canal;
  private NotificacionService $notificaciones;

  protected function setUp(): void
  {
    parent::setUp();
    $this->canal = new CanalDePrueba('email');
    $this->notificaciones = $this->make(NotificacionService::class);
    $this->notificaciones->usarCanal($this->canal);
  }

  private int $secuencia = 0;

  private function turno(string $fecha, string $hora = '09:30', ?string $email = 'juan@mail.com'): int
  {
    $n = ++$this->secuencia;
    $cliente = $this->crearCliente((string) (30111220 + $n));
    if ($email !== null) {
      $this->db->prepare('UPDATE personas p JOIN clientes c ON c.persona_id = p.id SET p.email = ? WHERE c.id = ?')->execute([$email, $cliente]);
    }
    $equipo = $this->crearEquipo($cliente, sprintf('AB%03dCD', 122 + $n));

    return $this->make(TurnoService::class)->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => $fecha, 'hora' => $hora]);
  }

  public function testConfirmacionUsaLaPlantillaYElLinkDelTurno(): void
  {
    $id = $this->turno(date('Y-m-d', strtotime('+3 days')));

    $this->assertSame('email', $this->notificaciones->enviarConfirmacion($id));

    $mensaje = $this->canal->enviados[0]['mensaje'];
    $token = $this->make(TurnoRepository::class)->token($id);
    $this->assertStringContainsString('Confirmá tu turno en Servicio Técnico PC', $mensaje->asunto);
    $this->assertStringContainsString('Hola Juan', $mensaje->texto);
    $this->assertStringContainsString("/turno/{$token}", $mensaje->texto);
    $this->assertNotNull($this->make(TurnoRepository::class)->detalle($id)['confirmacion_enviada']);
  }

  public function testPlantillasConfigurables(): void
  {
    $this->configurar('mensajes', ['email_recordatorio_asunto' => 'Te esperamos, {cliente}', 'email_recordatorio' => 'Tu {equipo} a las {hora}']);
    $id = $this->turno(date('Y-m-d', strtotime('+3 days')), '11:15');

    $this->notificaciones->enviarRecordatorio($id);

    $this->assertSame('Te esperamos, Juan', $this->canal->enviados[0]['mensaje']->asunto);
    $this->assertSame('Tu Notebook Marca AB123CD Modelo a las 11:15', $this->canal->enviados[0]['mensaje']->texto);
  }

  public function testSinEmailNoSeEnviaYQuedaRegistrado(): void
  {
    $id = $this->turno(date('Y-m-d', strtotime('+3 days')), email: null);

    $this->assertNull($this->notificaciones->enviarConfirmacion($id));
    $this->assertSame([], $this->canal->enviados);
    $this->assertSame('error', $this->db->query('SELECT estado FROM notificaciones')->fetchColumn());
  }

  public function testWhatsappManualUsaCodigoDePaisYPlantilla(): void
  {
    $this->configurar('mensajes', ['whatsapp_recordatorio' => 'Hola {cliente}, turno {fecha} {hora}']);
    $id = $this->turno(date('Y-m-d', strtotime('+1 day')));

    $url = $this->notificaciones->whatsappManual($id);

    $this->assertStringStartsWith('https://wa.me/5491122334455?text=', $url);
    $this->assertStringEndsWith(rawurlencode('09:30'), $url);
    $this->assertSame('whatsapp', $this->make(TurnoRepository::class)->detalle($id)['recordatorio_canal']);
  }

  public function testRecordatoriosAutomaticosSoloEnHorarioHabilYParaElProximoDiaHabil(): void
  {
    // Viernes 2026-10-09. Horario: lunes a viernes 8 a 18, sábado y domingo cerrado; lunes 12 feriado.
    $semana = ['desde' => '08:00', 'hasta' => '18:00'];
    $this->configurar('turnos', [
      'horario' => ['1' => $semana, '2' => $semana, '3' => $semana, '4' => $semana, '5' => $semana, '6' => null, '7' => null],
      'feriados' => ['2026-10-12'], 'recordatorio_hora' => '10:00',
    ]);
    $martes = $this->turno('2026-10-13');
    $miercoles = $this->turno('2026-10-14');

    $antesDeHora = $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 09:00'));
    $this->assertSame('Todavía no es la hora de envío.', $antesDeHora['omitido']);

    $finDeSemana = $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-10 11:00'));
    $this->assertSame('Fuera del horario de atención.', $finDeSemana['omitido']);

    // Viernes 10:30: el próximo día hábil es el martes 13 (sábado/domingo cerrado y lunes feriado).
    $viernes = $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 10:30'));
    $this->assertSame(1, $viernes['enviados']);
    $this->assertSame('juan@mail.com', $this->canal->enviados[0]['destinatario']->email);

    // Otra ejecución no lo repite.
    $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 11:00'));
    $this->assertCount(1, $this->canal->enviados);

    $repo = $this->make(TurnoRepository::class);
    $this->assertSame('email', $repo->detalle($martes)['recordatorio_canal']);
    $this->assertNull($repo->detalle($miercoles)['recordatorio_enviado']);
  }

  public function testSiElEnvioFallaSeReintentaEnLaProximaEjecucion(): void
  {
    $semana = ['desde' => '08:00', 'hasta' => '18:00'];
    $this->configurar('turnos', ['horario' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], $semana), 'feriados' => [], 'recordatorio_hora' => '08:00']);
    $id = $this->turno('2026-10-10');

    $this->canal->fallar = true;
    $resultado = $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 10:00'));
    $this->assertSame(1, $resultado['errores']);
    $this->assertNull($this->make(TurnoRepository::class)->detalle($id)['recordatorio_enviado']);

    $this->canal->fallar = false;
    $this->assertSame(1, $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 10:15'))['enviados']);
  }

  public function testUnaReservaQueQuedoTrabadaSeReintenta(): void
  {
    $semana = ['desde' => '08:00', 'hasta' => '18:00'];
    $this->configurar('turnos', ['horario' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], $semana), 'feriados' => [], 'recordatorio_hora' => '08:00']);
    $id = $this->turno('2026-10-10');

    // Un proceso reservó el envío y se cortó: hace 5 minutos todavía puede estar enviando.
    $this->db->exec("UPDATE turnos SET recordatorio_canal = 'enviando', recordatorio_enviado = NOW() - INTERVAL 5 MINUTE WHERE id = {$id}");
    $this->assertSame(0, $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 10:00'))['enviados']);

    // Pasada media hora, la reserva se considera abandonada y se reintenta.
    $this->db->exec("UPDATE turnos SET recordatorio_enviado = NOW() - INTERVAL 31 MINUTE WHERE id = {$id}");
    $this->assertSame(1, $this->notificaciones->enviarRecordatoriosPendientes(new DateTimeImmutable('2026-10-09 10:15'))['enviados']);
    $this->assertSame('email', $this->make(TurnoRepository::class)->detalle($id)['recordatorio_canal']);
  }
}
