<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Repositories\PanelRepository;
use App\Services\EntregaService;
use App\Services\OrdenService;

/** Tablero del local: los equipos que están en el local, en columnas por etapa. */
final class TableroTest extends HttpTestCase
{
  /** @return array<string, int> nombre => id de orden, una por etapa */
  private function ordenes(): array
  {
    $ordenes = $this->make(OrdenService::class);
    $servicio = $this->crearServicio('Limpieza', 30000);
    $ids = [];
    foreach (['recibida' => 'AA111AA', 'en_reparacion' => 'AA222AA', 'lista' => 'AA333AA', 'entregada' => 'AA444AA', 'cancelada' => 'AA555AA'] as $nombre => $serie) {
      $ids[$nombre] = $ordenes->guardar($this->crearEquipo($this->crearCliente((string) random_int(20000000, 49999999)), $serie), [$servicio => ['cantidad' => '1']], []);
    }
    $ordenes->cambiarEstado($ids['en_reparacion'], 'en_proceso');
    $ordenes->cambiarEstado($ids['lista'], 'finalizado');
    $ordenes->cambiarEstado($ids['entregada'], 'finalizado');
    $this->make(EntregaService::class)->entregar($ids['entregada'], null, null, null);
    $ordenes->cambiarEstado($ids['cancelada'], 'cancelado');

    return $ids;
  }

  public function testMuestraLosEquiposQueEstanEnElLocalPorEtapa(): void
  {
    $ids = $this->ordenes();

    $porEstado = [];
    foreach ($this->make(OrdenRepository::class)->tablero() as $o) {
      $porEstado[$o['estado']][] = (int) $o['id'];
    }

    ksort($porEstado);
    $this->assertSame(['en_proceso' => [$ids['en_reparacion']], 'finalizado' => [$ids['lista']], 'pendiente' => [$ids['recibida']]], $porEstado);
    $this->assertSame(['pendiente' => 1, 'en_proceso' => 1, 'listo' => 1], $this->make(PanelRepository::class)->ordenesAbiertasPorEstado());
  }

  public function testLaPantallaDelTableroYElMenuMoverA(): void
  {
    $this->ingresarComo();
    $ids = $this->ordenes();

    $html = $this->pedir('GET', '/ordenes/tablero')->html();
    $this->assertStringContainsString('AA333AA', $html);
    $this->assertStringContainsString("/ordenes/{$ids['lista']}/entregar", $html);
    $this->assertStringNotContainsString('AA444AA', $html, 'la entregada ya no está en el local');
    $this->assertStringNotContainsString('AA555AA', $html);

    // "Mover a…" sin arrastrar: vuelve al tablero, con "Deshacer" que también vuelve al tablero.
    $r = $this->pedir('POST', "/ordenes/{$ids['recibida']}/estado", ['estado' => 'en_proceso', 'volver' => 'tablero']);
    $this->assertSame('/ordenes/tablero', $r->destino);
    $this->assertSame('tablero', json_decode($this->mensajes('deshacer')[0], true)['campos']['volver']);
  }

  public function testArrastrarNoDejaMensajesGuardadosSalvoQueLaPaginaSeRecargue(): void
  {
    $this->ingresarComo();
    $ids = $this->ordenes();

    $this->pedir('POST', "/ordenes/{$ids['recibida']}/estado", ['estado' => 'en_proceso'], ajax: true);
    $this->assertSame([], $this->mensajes('success'), 'si no, aparecían todos juntos en la pantalla siguiente');

    $this->pedir('POST', "/ordenes/{$ids['en_reparacion']}/estado", ['estado' => 'finalizado', 'recargar' => '1'], ajax: true);
    $this->assertCount(1, $this->mensajes('success'));
  }
}
