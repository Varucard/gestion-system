<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\PanelRepository;
use App\Services\NotificacionService;
use App\Services\OrdenService;

/** "Para no olvidarse" del inicio: equipos sin retirar, presupuestos sin respuesta, mantenimientos. */
final class AlertasTest extends HttpTestCase
{
  private function orden(string $serie): int
  {
    $equipo = $this->crearEquipo($this->crearCliente((string) random_int(20000000, 49999999)), $serie);

    return $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio("Limpieza {$serie}", 10000) => ['cantidad' => '1']], []);
  }

  public function testEquipoListoSinRetirarDespuesDeLosDiasConfigurados(): void
  {
    $vieja = $this->orden('AA111AA');
    $nueva = $this->orden('AA222AA');
    foreach ([$vieja, $nueva] as $id) {
      $this->make(OrdenService::class)->cambiarEstado($id, 'finalizado');
    }
    $this->db->exec("UPDATE ordenes SET fecha_realizado = CURDATE() - INTERVAL 5 DAY WHERE id = {$vieja}");

    $this->assertSame([$vieja], array_map('intval', array_column($this->make(PanelRepository::class)->listosSinRetirar(3), 'id')));
  }

  public function testPresupuestoSinRespuesta(): void
  {
    $sinResponder = $this->orden('AA111AA');
    $aceptado = $this->orden('AA222AA');
    $this->db->exec("UPDATE ordenes SET presupuesto_enviado = NOW() - INTERVAL 4 DAY WHERE id IN ({$sinResponder}, {$aceptado})");
    $this->db->exec("UPDATE ordenes SET presupuesto_respuesta = 'aceptado' WHERE id = {$aceptado}");

    $this->assertSame([$sinResponder], array_map('intval', array_column($this->make(PanelRepository::class)->presupuestosSinRespuesta(3), 'id')));
  }

  public function testElInicioLasMuestraConSuWhatsapp(): void
  {
    $this->ingresarComo();
    $id = $this->orden('AA111AA');
    $this->db->exec("UPDATE ordenes SET presupuesto_enviado = NOW() - INTERVAL 4 DAY WHERE id = {$id}");

    $html = $this->pedir('GET', '/')->html();

    $this->assertStringContainsString('Para no olvidarse', $html);
    $this->assertStringContainsString("/ordenes/{$id}/whatsapp/presupuesto", $html);
  }

  public function testMantenimientoAvisadoPorWhatsappYaNoFiguraSinAvisar(): void
  {
    $id = $this->orden('AA111AA');
    $this->db->exec("UPDATE ordenes SET estado = 'finalizado', proximo_mantenimiento_fecha = CURDATE() + INTERVAL 10 DAY WHERE id = {$id}");

    $this->make(NotificacionService::class)->whatsappOrden($id, 'mantenimiento');

    $this->assertNotNull($this->db->query("SELECT proximo_mantenimiento_avisado FROM ordenes WHERE id = {$id}")->fetchColumn());
  }
}
