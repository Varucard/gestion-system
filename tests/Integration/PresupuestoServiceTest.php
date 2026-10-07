<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Services\NotificacionService;
use App\Services\OrdenService;
use DateTimeImmutable;
use Tests\Support\CanalDePrueba;

final class PresupuestoServiceTest extends IntegrationTestCase
{
  private CanalDePrueba $canal;
  private NotificacionService $notificaciones;
  private int $equipo;
  private int $servicio;

  protected function setUp(): void
  {
    parent::setUp();
    $this->canal = new CanalDePrueba('email');
    $this->notificaciones = $this->make(NotificacionService::class);
    $this->notificaciones->usarCanal($this->canal);

    $cliente = $this->crearCliente();
    $this->db->prepare('UPDATE personas p JOIN clientes c ON c.persona_id = p.id SET p.email = ? WHERE c.id = ?')->execute(['juan@mail.com', $cliente]);
    $this->equipo = $this->crearEquipo($cliente);
    $this->servicio = $this->crearServicio('Formateo', 15000);
  }

  private function orden(array $detalle = []): int
  {
    return $this->make(OrdenService::class)->guardar($this->equipo, [$this->servicio], [], null, null, $detalle);
  }

  public function testEnviaElPresupuestoConPdfYLink(): void
  {
    $id = $this->orden();

    $this->assertSame('email', $this->notificaciones->enviarPresupuesto($id));

    $mensaje = $this->canal->enviados[0]['mensaje'];
    $token = $this->make(OrdenRepository::class)->token($id);
    $this->assertStringContainsString('Presupuesto N° ' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $mensaje->asunto);
    $this->assertStringContainsString('$ 15.000,00', $mensaje->texto);
    $this->assertStringContainsString("/presupuesto/{$token}", $mensaje->texto);
    $this->assertSame('application/pdf', $mensaje->adjuntos[0]['tipo']);
    $this->assertStringStartsWith('%PDF', $mensaje->adjuntos[0]['contenido']);
    $this->assertNotNull($this->make(OrdenRepository::class)->find($id)['presupuesto_enviado']);
  }

  public function testElClienteAceptaYLaOrdenPasaAEnProceso(): void
  {
    $id = $this->orden();
    $token = $this->make(OrdenRepository::class)->token($id);
    $ordenes = $this->make(OrdenService::class);

    $ordenes->responderPresupuesto($token, 'rechazar');
    $this->assertSame('rechazado', $this->make(OrdenRepository::class)->find($id)['presupuesto_respuesta']);

    // Puede cambiar de opinión mientras siga pendiente y vigente.
    $orden = $ordenes->responderPresupuesto($token, 'aceptar');
    $this->assertSame('aceptado', $orden['presupuesto_respuesta']);
    $this->assertSame('en_proceso', $orden['estado']);

    $this->expectExceptionMessage('Este presupuesto ya fue aceptado.');
    $ordenes->responderPresupuesto($token, 'rechazar');
  }

  public function testEditarLaOrdenAnulaLaRespuestaDelCliente(): void
  {
    $this->configurar('trabajo', ['aceptar_inicia_trabajo' => false]);
    $id = $this->orden();
    $repo = $this->make(OrdenRepository::class);
    $ordenes = $this->make(OrdenService::class);
    $ordenes->responderPresupuesto($repo->token($id), 'aceptar');

    // Guardar sin cambios en los ítems conserva la aceptación.
    $ordenes->guardar($this->equipo, [$this->servicio], [], $id, null, ['diagnostico' => 'Pastillas gastadas']);
    $this->assertSame('aceptado', $repo->find($id)['presupuesto_respuesta']);

    // Cambiar precio o ítems la deja sin efecto: el cliente aceptó otro monto.
    $ordenes->guardar($this->equipo, [$this->servicio => ['cantidad' => '1', 'precio' => '18000']], [], $id);
    $this->assertNull($repo->find($id)['presupuesto_respuesta']);

    // Y puede volver a responder sobre el presupuesto nuevo.
    $this->assertSame('aceptado', $ordenes->responderPresupuesto($repo->token($id), 'aceptar')['presupuesto_respuesta']);
  }

  public function testSiCambiaUnPresupuestoAceptadoSeAvisaYSePuedeVolverAAceptar(): void
  {
    $id = $this->orden();
    $repo = $this->make(OrdenRepository::class);
    $ordenes = $this->make(OrdenService::class);
    $token = $repo->token($id);
    $ordenes->responderPresupuesto($token, 'aceptar');
    $this->assertSame('en_proceso', $repo->find($id)['estado']);

    $ordenes->guardar($this->equipo, [$this->servicio => ['cantidad' => '1', 'precio' => '18000']], [], $id);
    $this->assertSame('email', $this->notificaciones->enviarPresupuestoModificado($id));

    $mensaje = $this->canal->enviados[0]['mensaje'];
    $this->assertStringContainsString('Cambió el presupuesto', $mensaje->asunto);
    $this->assertStringContainsString('$ 18.000,00', $mensaje->texto);
    $this->assertStringContainsString("/presupuesto/{$token}", $mensaje->texto);
    $this->assertStringStartsWith('%PDF', $mensaje->adjuntos[0]['contenido']);

    // Aunque la orden siga en proceso, el cliente puede aceptar el presupuesto nuevo.
    $this->assertSame('aceptado', $ordenes->responderPresupuesto($token, 'aceptar')['presupuesto_respuesta']);

    $ordenes->cambiarEstado($id, 'finalizado');
    $repo->anularRespuestaPresupuesto($id);
    $this->assertValidationError(fn() => $ordenes->responderPresupuesto($token, 'aceptar'), 'finalizado o cancelado');
  }

  public function testAvisoDeEquipoListoConSaldoYUnaSolaVez(): void
  {
    $id = $this->orden();
    $this->make(\App\Services\PagoService::class)->registrar($id, ['monto' => '5000', 'forma_pago' => 'Contado'], null);

    $this->assertTrue($this->notificaciones->avisoListoActivo());
    $this->assertFalse($this->notificaciones->equipoListoAvisado($id));
    $this->assertSame('email', $this->notificaciones->avisarEquipoListo($id));

    $mensaje = $this->canal->enviados[0]['mensaje'];
    $this->assertStringContainsString('está listo', $mensaje->asunto);
    $this->assertStringContainsString('Saldo a abonar: $ 10.000,00', $mensaje->texto);
    $this->assertTrue($this->notificaciones->equipoListoAvisado($id), 'Queda registrado: no se repite si la orden se vuelve a finalizar');

    $this->configurar('notificaciones', ['avisar_listo' => false]);
    $this->assertFalse($this->make(NotificacionService::class)->avisoListoActivo());
  }

  public function testReenviarConservaLaAceptacionYSoloSeEnviaConLaOrdenAbierta(): void
  {
    $this->configurar('trabajo', ['aceptar_inicia_trabajo' => false]);
    $id = $this->orden();
    $repo = $this->make(OrdenRepository::class);
    $this->make(OrdenService::class)->responderPresupuesto($repo->token($id), 'aceptar');

    $this->notificaciones->enviarPresupuesto($id);
    $this->assertSame('aceptado', $repo->find($id)['presupuesto_respuesta']);

    $this->make(OrdenService::class)->cambiarEstado($id, 'cancelado');
    $this->assertValidationError(fn() => $this->notificaciones->enviarPresupuesto($id), 'pendientes o en proceso');
  }

  public function testLaVigenciaSeCuentaDesdeElEnvio(): void
  {
    $this->configurar('trabajo', ['validez' => 10]);
    $id = $this->orden();
    $this->db->exec("UPDATE ordenes SET created_at = NOW() - INTERVAL 15 DAY WHERE id = {$id}");

    // Creada hace 15 días pero enviada hoy: sigue vigente.
    $this->notificaciones->enviarPresupuesto($id);
    $orden = $this->make(OrdenService::class)->responderPresupuesto($this->make(OrdenRepository::class)->token($id), 'aceptar');
    $this->assertSame('aceptado', $orden['presupuesto_respuesta']);
  }

  public function testNoSeAceptaUnPresupuestoVencido(): void
  {
    $this->configurar('trabajo', ['validez' => 10]);
    $id = $this->orden();
    $this->db->exec("UPDATE ordenes SET created_at = NOW() - INTERVAL 11 DAY WHERE id = {$id}");

    $this->expectExceptionMessage('El presupuesto venció');
    $this->make(OrdenService::class)->responderPresupuesto($this->make(OrdenRepository::class)->token($id), 'aceptar');
  }

  public function testAvisoDeProximoMantenimientoSoloParaLaUltimaOrdenFinalizada(): void
  {
    $semana = ['desde' => '08:00', 'hasta' => '18:00'];
    $this->configurar('turnos', ['horario' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], $semana), 'feriados' => [], 'recordatorio_hora' => '08:00']);
    $this->configurar('mantenimiento', ['aviso_dias_antes' => 7]);
    $ahora = new DateTimeImmutable('today 10:00');
    $ordenes = $this->make(OrdenService::class);

    $vieja = $this->orden(['proximo_mantenimiento_fecha' => $ahora->modify('+3 days')->format('Y-m-d')]);
    $ordenes->cambiarEstado($vieja, 'finalizado');

    $resultado = $this->notificaciones->enviarAvisosMantenimiento($ahora);
    $this->assertSame(1, $resultado['enviados']);
    $this->assertStringContainsString($ahora->modify('+3 days')->format('d/m/Y'), $this->canal->enviados[0]['mensaje']->texto);

    // No se repite.
    $this->assertSame(0, $this->notificaciones->enviarAvisosMantenimiento($ahora)['enviados']);

    // Si el equipo vuelve a entrar (orden más nueva), la anterior ya no se avisa.
    $otra = $this->orden(['proximo_mantenimiento_fecha' => $ahora->modify('+5 days')->format('Y-m-d')]);
    $ordenes->cambiarEstado($otra, 'finalizado');
    $this->db->exec("UPDATE ordenes SET proximo_mantenimiento_avisado = NULL WHERE id = {$vieja}");
    $this->notificaciones->enviarAvisosMantenimiento($ahora);
    $this->assertNull($this->make(OrdenRepository::class)->find($vieja)['proximo_mantenimiento_avisado']);
    $this->assertNotNull($this->make(OrdenRepository::class)->find($otra)['proximo_mantenimiento_avisado']);
  }

  public function testElLinkDelPresupuestoVence(): void
  {
    $id = $this->orden();
    $repo = $this->make(OrdenRepository::class);
    $token = $repo->token($id);
    $this->assertSame($id, $repo->idPorToken($token));

    $this->db->exec("UPDATE ordenes SET created_at = NOW() - INTERVAL 61 DAY WHERE id = {$id}");
    $this->assertNull($repo->idPorToken($token));

    // Reenviarlo lo vuelve a habilitar.
    $this->notificaciones->enviarPresupuesto($id);
    $this->assertSame($id, $repo->idPorToken($token));
  }
}
