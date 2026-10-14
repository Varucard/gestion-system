<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Repositories\ReporteRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\StockRepository;
use App\Services\DocumentoService;
use App\Services\OrdenService;
use App\Services\StockService;

/** Repuestos cobrados a costo (sin ganancia) y repuestos que trae el cliente. */
final class RepuestosACostoYDelClienteTest extends IntegrationTestCase
{
  private int $equipo;
  private int $servicio;

  protected function setUp(): void
  {
    parent::setUp();
    $this->equipo = $this->crearEquipo($this->crearCliente());
    $this->servicio = $this->crearServicio('Cambio de bomba de agua', 30000);
  }

  private function repuestoConCosto(string $nombre, float $precio, float $costo): int
  {
    $id = $this->crearRepuesto($nombre, $precio);
    $this->make(RepuestoRepository::class)->setCosto($id, $costo);

    return $id;
  }

  /** @return array<string, array<string, mixed>> ítems de la orden indexados por nombre */
  private function items(int $ordenId): array
  {
    $items = [];
    foreach ($this->make(OrdenRepository::class)->items($ordenId) as $item) {
      $items[$item['servicio_nombre'] ?? $item['repuesto_nombre'] ?? $item['descripcion']] = $item;
    }

    return $items;
  }

  public function testACostoSeCobraElCostoYLoConservaAunqueCambie(): void
  {
    $bomba = $this->repuestoConCosto('Bomba de agua', 14000, 10000);
    $ordenes = $this->make(OrdenService::class);

    // El precio que llegue del formulario no cuenta: a costo es el costo.
    $id = $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1', 'precio' => '99999', 'modo' => 'costo']]);
    $item = $this->items($id)['Bomba de agua'];
    $this->assertSame('10000.00', $item['precio_unitario']);
    $this->assertSame(1, (int) $item['a_costo']);
    $this->assertSame('40000.00', $this->make(OrdenRepository::class)->find($id)['total']);

    // Si después sube el costo, la orden conserva el del momento en que se marcó.
    $this->make(RepuestoRepository::class)->setCosto($bomba, 12000);
    $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '2', 'modo' => 'costo']], $id);
    $this->assertSame('10000.00', $this->items($id)['Bomba de agua']['precio_unitario']);

    // Volver a "del negocio" sin precio toma el del catálogo, no el de costo.
    $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1', 'modo' => 'negocio']], $id);
    $item = $this->items($id)['Bomba de agua'];
    $this->assertSame('14000.00', $item['precio_unitario']);
    $this->assertSame(0, (int) $item['a_costo']);
  }

  public function testACostoExigeQueElRepuestoTengaCosto(): void
  {
    $bomba = $this->crearRepuesto('Bomba de agua', 14000);

    $this->assertValidationError(
      fn() => $this->make(OrdenService::class)->guardar($this->equipo, [], [$bomba => ['modo' => 'costo']]),
      'no lo tiene: Bomba de agua',
    );
  }

  public function testLoQueTraeElClienteVaSinPrecioYNoMueveStock(): void
  {
    $bomba = $this->repuestoConCosto('Bomba de agua', 14000, 10000);
    $correa = $this->crearRepuesto('Correa de distribución', 9000);
    $this->make(StockService::class)->ingresar($bomba, '5', null, null, null);
    $this->make(StockService::class)->ingresar($correa, '5', null, null, null);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar(
      $this->equipo,
      [$this->servicio],
      [$bomba => ['cantidad' => '1', 'precio' => '14000', 'modo' => 'cliente'], $correa => ['cantidad' => '1']],
      piezasCliente: [['descripcion' => '  Termostato   original ', 'cantidad' => '1']],
    );

    $items = $this->items($id);
    $this->assertSame('0.00', $items['Bomba de agua']['precio_unitario']);
    $this->assertSame(1, (int) $items['Bomba de agua']['provisto_cliente']);
    $this->assertSame('Termostato original', $items['Termostato original']['descripcion']);
    $this->assertSame(1, (int) $items['Termostato original']['provisto_cliente']);
    $this->assertSame('Repuesto: Termostato original (provisto por el cliente)', item_orden($items['Termostato original']));
    $this->assertSame('39000.00', $this->make(OrdenRepository::class)->find($id)['total']); // servicio + correa

    // Solo la correa sale del stock; lo del cliente no se toca ni al finalizar ni al reabrir.
    $ordenes->cambiarEstado($id, 'finalizado');
    $this->assertSame('5.00', $this->make(RepuestoRepository::class)->find($bomba)['stock_actual']);
    $this->assertSame('4.00', $this->make(RepuestoRepository::class)->find($correa)['stock_actual']);
    $ordenes->cambiarEstado($id, 'en_proceso');
    $this->assertSame('5.00', $this->make(RepuestoRepository::class)->find($correa)['stock_actual']);
    $this->assertSame(['ingreso'], array_column($this->make(StockRepository::class)->historial($bomba), 'tipo'));

    // Los listados (y el portal del cliente) muestran aparte lo que trajo el cliente.
    $fila = $this->make(OrdenRepository::class)->porEquipo($this->equipo)[0];
    $this->assertSame('Correa de distribución', $fila['repuestos']);
    $this->assertSame('Bomba de agua, Termostato original', $fila['repuestos_cliente']);
  }

  public function testSinStockSuficienteNoFrenaPorLoQueTraeElCliente(): void
  {
    $this->configurar('stock', ['permitir_negativo' => false]);
    $bomba = $this->crearRepuesto('Bomba de agua', 14000);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar($this->equipo, [], [$bomba => ['modo' => 'cliente']]);

    $this->assertSame('finalizado', $ordenes->cambiarEstado($id, 'finalizado')->value);
  }

  public function testLaPiezaDelClienteNecesitaDescripcionYCantidad(): void
  {
    $ordenes = $this->make(OrdenService::class);

    $this->assertValidationError(
      fn() => $ordenes->guardar($this->equipo, [$this->servicio], [], piezasCliente: [['descripcion' => ' ', 'cantidad' => '1']]),
      'Escribí qué repuesto trae el cliente',
    );
    $this->assertValidationError(
      fn() => $ordenes->guardar($this->equipo, [$this->servicio], [], piezasCliente: [['descripcion' => 'Termostato', 'cantidad' => '0']]),
      'debe ser mayor a 0',
    );
  }

  public function testPasarUnRepuestoAlClienteAnulaLaAceptacionDelPresupuesto(): void
  {
    $this->configurar('trabajo', ['aceptar_inicia_trabajo' => false]);
    $bomba = $this->crearRepuesto('Bomba de agua', 14000);
    $repo = $this->make(OrdenRepository::class);
    $ordenes = $this->make(OrdenService::class);
    $id = $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1']]);
    $ordenes->responderPresupuesto($repo->token($id), 'aceptar');

    $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1', 'modo' => 'cliente']], $id);
    $this->assertNull($repo->find($id)['presupuesto_respuesta']);

    // Sumar una pieza escrita a mano también cambia lo que el cliente aceptó.
    $ordenes->responderPresupuesto($repo->token($id), 'aceptar');
    $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1', 'modo' => 'cliente']], $id, piezasCliente: [['descripcion' => 'Termostato']]);
    $this->assertNull($repo->find($id)['presupuesto_respuesta']);
  }

  public function testElPresupuestoAvisaLaGarantiaYLosReportesNoCuentanLoDelCliente(): void
  {
    $bomba = $this->crearRepuesto('Bomba de agua', 14000);
    $ordenes = $this->make(OrdenService::class);
    $delNegocio = $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1']]);
    $delCliente = $ordenes->guardar($this->equipo, [$this->servicio], [$bomba => ['cantidad' => '1', 'modo' => 'cliente']]);

    $documentos = $this->make(DocumentoService::class);
    $this->assertFalse($documentos->datos($delNegocio)['provistosCliente']);
    $datos = $documentos->datos($delCliente);
    $this->assertTrue($datos['provistosCliente']);
    $this->assertStringContainsString('no cubre los repuestos provistos por el cliente', $datos['trabajo']['garantia_repuestos_cliente']);
    $this->assertSame(30000.0, (float) $datos['total']);

    $vendidos = $this->make(ReporteRepository::class)->masVendidos('repuesto', date('Y-m-d'), date('Y-m-d'));
    $this->assertSame([['nombre' => 'Bomba de agua', 'ordenes' => 1, 'cantidad' => '1.00', 'total' => '14000.00']], array_map(
      fn($fila) => [...$fila, 'ordenes' => (int) $fila['ordenes']],
      $vendidos,
    ));
  }
}
