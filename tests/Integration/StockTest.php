<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\OrdenRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\StockRepository;
use App\Services\OrdenService;
use App\Services\ProveedorService;
use App\Services\StockService;

final class StockTest extends IntegrationTestCase
{
  private function stock(int $repuestoId): float
  {
    return (float) $this->make(RepuestoRepository::class)->find($repuestoId)['stock_actual'];
  }

  public function testIngresoYAjusteRegistranMovimientos(): void
  {
    $proveedor = $this->make(ProveedorService::class)->guardar(['nombre' => 'Repuestos Sur', 'cuit' => '30-12345678-9']);
    $filtro = $this->crearRepuesto('Filtro', 100);
    $stock = $this->make(StockService::class);

    $this->assertSame(10.0, $stock->ingresar($filtro, '10', $proveedor, 'Factura 123', null));
    $this->assertSame(7.5, $stock->ajustar($filtro, '7,5', 'Inventario', null));

    $historial = $this->make(StockRepository::class)->historial($filtro);
    $this->assertSame(['ajuste', 'ingreso'], array_column($historial, 'tipo'));
    $this->assertSame('-2.50', $historial[0]['cantidad']);
    $this->assertSame('Repuestos Sur', $historial[1]['proveedor']);
  }

  public function testElAjusteExigeMotivoYElIngresoCantidadPositiva(): void
  {
    $filtro = $this->crearRepuesto('Filtro', 100);
    $stock = $this->make(StockService::class);

    try {
      $stock->ingresar($filtro, '0', null, null, null);
      $this->fail('Debía rechazar cantidad 0');
    } catch (ValidationException) {
    }

    $this->expectExceptionMessage('Indicá el motivo');
    $stock->ajustar($filtro, '5', '', null);
  }

  public function testLaOrdenDescuentaAlFinalizarYReponeAlReabrir(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Limpieza interna', 1000);
    $limpieza = $this->crearRepuesto('Pasta térmica (jeringa)', 200);
    $this->make(StockService::class)->ingresar($limpieza, '20', null, null, null);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar($equipo, [$servicio => ['cantidad' => '1']], [$limpieza => ['cantidad' => '4,5', 'precio' => '250']]);
    $orden = $this->make(OrdenRepository::class)->find($id);
    $this->assertSame('2125.00', $orden['total']); // 1000 + 4,5 × 250

    $ordenes->cambiarEstado($id, 'finalizado');
    $this->assertSame(15.5, $this->stock($limpieza));

    // Volver a "finalizado" no descuenta dos veces.
    $ordenes->cambiarEstado($id, 'finalizado');
    $this->assertSame(15.5, $this->stock($limpieza));

    $ordenes->cambiarEstado($id, 'cancelado');
    $this->assertSame(20.0, $this->stock($limpieza));
    $this->assertSame(['ingreso', 'egreso', 'ingreso'], array_column($this->make(StockRepository::class)->historial($limpieza), 'tipo'));
  }

  public function testSinStockNegativoNoSeFinaliza(): void
  {
    $this->configurar('stock', ['permitir_negativo' => false]);
    $equipo = $this->crearEquipo($this->crearCliente());
    $filtro = $this->crearRepuesto('Filtro', 100);
    $this->make(StockService::class)->ingresar($filtro, '1', null, null, null);
    $ordenes = $this->make(OrdenService::class);
    $id = $ordenes->guardar($equipo, [$this->crearServicio('Service', 500)], [$filtro => ['cantidad' => '2']]);

    try {
      $ordenes->cambiarEstado($id, 'finalizado');
      $this->fail('Debía impedir finalizar sin stock');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('Filtro (hay 1, se necesitan 2)', $e->getMessage());
    }
    $this->assertSame(1.0, $this->stock($filtro));

    $this->make(StockService::class)->ingresar($filtro, '1', null, null, null);
    $ordenes->cambiarEstado($id, 'finalizado');
    $this->assertSame(0.0, $this->stock($filtro));
  }

  public function testRechazaCantidadesYPreciosInvalidos(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Revisión', 1000);

    $this->expectExceptionMessage('Las cantidades deben ser mayores a 0');
    $this->make(OrdenService::class)->guardar($equipo, [$servicio => ['cantidad' => '0']], []);
  }

  public function testUnProveedorConRepuestosNoSeElimina(): void
  {
    $proveedor = $this->make(ProveedorService::class)->guardar(['nombre' => 'Distribuidora']);
    $this->make(\App\Services\RepuestoService::class)->guardar(['nombre' => 'Bujía', 'precio' => '10', 'proveedor_id' => $proveedor]);

    $this->expectExceptionMessage('Podés desactivarlo');
    $this->make(ProveedorService::class)->eliminar($proveedor);
  }
}
