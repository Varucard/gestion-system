<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AuditoriaRepository;
use App\Services\OrdenService;
use App\Services\PagoService;
use App\Services\ServicioService;

final class AuditoriaTest extends IntegrationTestCase
{
  public function testRegistraCambiosDeLaOrdenPagosYPrecios(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Formateo', 1000);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar($equipo, [$servicio], []);
    $this->make(PagoService::class)->registrar($id, ['monto' => '400', 'forma_pago' => 'Contado'], null);
    $ordenes->cambiarEstado($id, 'finalizado');
    $this->make(ServicioService::class)->guardar(['nombre' => 'Formateo', 'precio_base' => '1200'], $servicio);

    $historial = $this->make(AuditoriaRepository::class)->deEntidad('orden', $id);
    $this->assertSame(['cambiar_estado', 'registrar_pago', 'crear'], array_column($historial, 'accion'));
    $this->assertSame('Orden #' . $id . ': Pendiente → Finalizado', $historial[0]['descripcion']);
    $this->assertSame('Sistema', $historial[0]['usuario_nombre'], 'Sin sesión (CLI/tests) el actor es Sistema');

    $precio = $this->make(AuditoriaRepository::class)->deEntidad('servicio', $servicio)[0];
    $this->assertSame('cambiar_precio', $precio['accion']);
    $this->assertSame(['antes' => 1000, 'despues' => 1200], json_decode($precio['datos'], true));
  }

  public function testPaginacionBusquedaYFiltros(): void
  {
    for ($i = 1; $i <= 30; $i++) {
      $this->crearServicio("Servicio {$i}", $i);
    }
    $repo = $this->make(AuditoriaRepository::class);

    $pagina = $repo->paginar(['draw' => '3', 'start' => '10', 'length' => '10', 'order' => [['column' => '0', 'dir' => 'desc']]]);
    $this->assertSame(3, $pagina['draw']);
    $this->assertSame(30, $pagina['recordsTotal']);
    $this->assertCount(10, $pagina['filas']);

    // Cada palabra debe aparecer (en cualquier columna buscable).
    $this->assertSame(1, $repo->paginar(['search' => ['value' => 'creado servicio 27']])['recordsFiltered']);
    $this->assertSame(0, $repo->paginar(['search' => ['value' => 'servicio eliminado']])['recordsFiltered']);

    $this->assertSame(0, $repo->paginar(['entidad' => 'orden'])['recordsTotal']);
    $this->assertSame(0, $repo->paginar(['search' => ['value' => "' OR 1=1 --"]])['recordsFiltered']);
  }
}
