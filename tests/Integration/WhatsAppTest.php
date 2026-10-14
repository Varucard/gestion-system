<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Services\NotificacionService;
use App\Services\OrdenService;
use App\Services\TurnoService;
use Tests\Support\CanalDePrueba;

/**
 * Botones de WhatsApp: abren la conversación con el mensaje y el link armados. No hay API
 * de WhatsApp Business: los avisos automáticos son solo por email.
 */
final class WhatsAppTest extends HttpTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->configurar('notificaciones', ['whatsapp_abrir' => 'wame']);
  }

  public function testEnLaPcAbreLoElegidoYEnElCelularLaApp(): void
  {
    $config = ['whatsapp_abrir' => 'web'];
    $celular = 'Mozilla/5.0 (Linux; Android 14) Mobile Safari/537.36';
    $pc = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';

    $this->assertSame('web', whatsapp_modo($config, $pc));
    $this->assertSame('wame', whatsapp_modo($config, $celular));
    $this->assertSame('_blank', whatsapp_target('web'), 'WhatsApp Web no deja reutilizar la pestaña (COOP)');
    $this->assertSame('https://web.whatsapp.com/send?phone=5491122334455&text=Hola%20Juan', whatsapp_url('1122334455', 'Hola Juan', '54', 'web'));
    $this->assertSame('whatsapp://send?phone=5491122334455&text=Hola', whatsapp_url('1122334455', 'Hola', '54', 'app'));
    $this->assertSame('_self', whatsapp_target('app'));
    $this->assertSame('https://wa.me/5491122334455', whatsapp_url('1122334455'));
  }

  private function orden(bool $conItems = true): int
  {
    $equipo = $this->crearEquipo($this->crearCliente());

    return $this->make(OrdenService::class)->guardar($equipo, $conItems ? [$this->crearServicio('Alineación', 20000) => ['cantidad' => '1']] : [], []);
  }

  /** @return string el texto del mensaje que trae el link de wa.me */
  private static function texto(string $url): string
  {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query['text'];
  }

  public function testPresupuestoConElLinkParaAceptarloYCuentaComoEnvio(): void
  {
    $id = $this->orden();

    $url = $this->make(NotificacionService::class)->whatsappOrden($id, 'presupuesto');

    $this->assertStringStartsWith('https://wa.me/5491122334455?text=', $url);
    $token = $this->make(OrdenRepository::class)->token($id);
    $this->assertStringContainsString("/presupuesto/{$token}", self::texto($url));
    $this->assertNotNull($this->make(OrdenRepository::class)->find($id)['presupuesto_enviado']);
    $this->assertSame(
      ['presupuesto', 'whatsapp_manual', 'enviado'],
      array_values($this->db->query("SELECT tipo, canal, estado FROM notificaciones WHERE orden_id = {$id}")->fetch()),
    );
  }

  public function testListoLlevaElLinkParaSeguirElAutoYSoloConElTrabajoTerminado(): void
  {
    $id = $this->orden();
    $notificaciones = $this->make(NotificacionService::class);

    $this->assertValidationError(fn() => $notificaciones->whatsappOrden($id, 'listo'), 'cuando el trabajo está terminado');

    $this->make(OrdenService::class)->cambiarEstado($id, 'finalizado');
    $texto = self::texto($notificaciones->whatsappOrden($id, 'listo'));
    $this->assertStringContainsString('/seguimiento', $texto);
    $this->assertStringContainsString('20.000,00', $texto);
    // Ya se le avisó: al terminar de nuevo no se repite por email.
    $this->assertTrue($notificaciones->equipoListoAvisado($id));
  }

  public function testSinTrabajosNoHayPresupuestoQueMandar(): void
  {
    $id = $this->orden(conItems: false);

    $this->assertValidationError(fn() => $this->make(NotificacionService::class)->whatsappOrden($id, 'presupuesto'), 'Primero cargá los trabajos');
    $this->assertValidationError(fn() => $this->make(NotificacionService::class)->whatsappOrden($id, 'otro'), 'no existe');
  }

  public function testElBotonAbreWhatsappEnOtraPestanaOMuestraElMotivo(): void
  {
    $this->ingresarComo();
    $id = $this->orden();

    $r = $this->pedir('POST', "/ordenes/{$id}/whatsapp/presupuesto");
    $this->assertSame(303, $r->estado);
    $this->assertStringStartsWith('https://wa.me/', $r->destino);

    $r = $this->pedir('POST', "/ordenes/{$id}/whatsapp/listo");
    $this->assertSame("/ordenes/{$id}", $r->destino);
    $this->assertStringContainsString('terminado', $this->mensajes('error')[0]);
  }

  public function testTurnoSinConfirmarMandaElLinkParaConfirmar(): void
  {
    $cliente = $this->crearCliente();
    $turno = $this->make(TurnoService::class)->guardar([
      'cliente_id' => $cliente, 'equipo_id' => $this->crearEquipo($cliente), 'fecha' => date('Y-m-d', strtotime('+2 days')), 'hora' => '09:00',
    ]);

    $texto = self::texto($this->make(NotificacionService::class)->whatsappManual($turno, 'confirmacion'));

    $this->assertStringContainsString('/turno/' . $this->make(TurnoRepository::class)->token($turno), $texto);
    $this->assertNotNull($this->make(TurnoRepository::class)->detalle($turno)['confirmacion_enviada']);
    $this->assertNull($this->make(TurnoRepository::class)->detalle($turno)['recordatorio_canal']);
  }

  public function testUnaConfiguracionViejaConWhatsappBusinessNoRompeLosAvisos(): void
  {
    $this->configurar('notificaciones', ['canales' => ['whatsapp', 'email']]);
    $notificaciones = $this->make(NotificacionService::class);
    $notificaciones->usarCanal(new CanalDePrueba('email'));

    $this->assertSame(['email'], array_map(fn($c) => $c->nombre(), $notificaciones->canalesDisponibles()));
  }

  public function testElMensajeDeWhatsappDelPresupuestoTieneQueLlevarElLink(): void
  {
    $mensajes = (require dirname(__DIR__, 2) . '/config/negocio.php')['mensajes'];
    $mensajes['whatsapp_presupuesto'] = 'Hola {cliente}, te mandamos el presupuesto.';

    $this->assertValidationError(fn() => $this->make(\App\Services\ConfiguracionService::class)->guardar('mensajes', $mensajes), 'debe incluir {link_presupuesto}');
  }

  public function testAlAbrirloQuedaElAvisoYUnaLineaEnElHistorial(): void
  {
    $this->ingresarComo();
    $id = $this->orden();

    $this->pedir('POST', "/ordenes/{$id}/whatsapp/presupuesto");

    $this->assertStringContainsString('Se abrió WhatsApp', $this->mensajes('success')[0]);
    $historial = array_column($this->make(\App\Repositories\AuditoriaRepository::class)->deEntidad('orden', $id), 'descripcion');
    $this->assertContains("Orden #{$id}: se abrió WhatsApp para mandarle al cliente: presupuesto", $historial);
  }

  public function testRecordatorioDelPresupuestoYSaldoSoloSiSeDebe(): void
  {
    $id = $this->orden();
    $notificaciones = $this->make(NotificacionService::class);

    $this->assertStringContainsString('¿pudiste ver el presupuesto', self::texto($notificaciones->whatsappOrden($id, 'presupuesto_recordatorio')));

    $this->make(OrdenService::class)->cambiarEstado($id, 'finalizado');
    $this->assertStringContainsString('Saldo a abonar: $ 20.000,00', self::texto($notificaciones->whatsappOrden($id, 'listo')));
    $this->make(\App\Services\PagoService::class)->registrar($id, ['monto' => '20000', 'forma_pago' => 'Contado'], null);
    $texto = self::texto($notificaciones->whatsappOrden($id, 'listo'));
    $this->assertStringContainsString('Ya está todo pago', $texto);
    $this->assertStringNotContainsString('0,00', $texto);
  }
}
