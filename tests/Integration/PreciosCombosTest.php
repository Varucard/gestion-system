<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\AuditoriaRepository;
use App\Repositories\ComboRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Services\ComboService;
use App\Services\PrecioService;
use App\Services\ProveedorService;
use App\Services\RepuestoService;
use App\Services\StockService;

final class PreciosCombosTest extends IntegrationTestCase
{
  public function testVistaPreviaNoGuardaYAplicarRespetaLaSeleccion(): void
  {
    $limpieza = $this->crearServicio('Limpieza interna', 10000);
    $formateo = $this->crearServicio('Formateo', 20000);
    $filtro = $this->crearRepuesto('Filtro', 5000);
    $precios = $this->make(PrecioService::class);
    $params = ['aplicar_a' => 'ambos', 'porcentaje' => '10', 'redondeo' => '100'];

    $vista = $precios->vistaPrevia($params);
    $this->assertCount(3, $vista['items']);
    $this->assertSame(10000.0, (float) $this->make(ServicioRepository::class)->find($limpieza)['precio_base'], 'La vista previa no guarda');

    // Se destilda el filtro: solo se actualizan los dos servicios.
    $vistos = ['actual' => ["servicio:{$limpieza}" => '10000.00', "servicio:{$formateo}" => '20000.00', "repuesto:{$filtro}" => '5000.00']];
    $this->assertSame(2, $precios->aplicar([...$params, ...$vistos, 'ids' => ["servicio:{$limpieza}", "servicio:{$formateo}"]]));
    $this->assertSame(11000.0, (float) $this->make(ServicioRepository::class)->find($limpieza)['precio_base']);
    $this->assertSame(22000.0, (float) $this->make(ServicioRepository::class)->find($formateo)['precio_base']);
    $this->assertSame(5000.0, (float) $this->make(RepuestoRepository::class)->find($filtro)['precio']);

    $auditoria = $this->make(AuditoriaRepository::class)->paginar(['entidad' => 'precios'])['filas'];
    $this->assertCount(1, $auditoria);
    $this->assertCount(2, json_decode($auditoria[0]['datos'], true)['cambios']);
  }

  public function testSinSeleccionNoModificaNadaYNoSeAplicaDosVeces(): void
  {
    $limpieza = $this->crearServicio('Limpieza interna', 10000);
    $filtro = $this->crearRepuesto('Filtro', 5000);
    $precios = $this->make(PrecioService::class);
    $params = ['aplicar_a' => 'ambos', 'porcentaje' => '10', 'redondeo' => '0',
      'actual' => ["servicio:{$limpieza}" => '10000.00', "repuesto:{$filtro}" => '5000.00']];

    // Todo destildado: el formulario no manda ids[] y no debe tocarse ningún precio.
    $this->assertValidationError(fn() => $precios->aplicar($params), 'No hay precios para modificar');
    $this->assertSame(10000.0, (float) $this->make(ServicioRepository::class)->find($limpieza)['precio_base']);

    $todos = [...$params, 'ids' => ["servicio:{$limpieza}", "repuesto:{$filtro}"]];
    $this->assertSame(2, $precios->aplicar($todos));

    // El mismo formulario enviado otra vez (doble click o volver atrás) no suma otro 10%.
    $this->assertValidationError(fn() => $precios->aplicar($todos), 'cambiaron desde la vista previa');
    $this->assertSame(11000.0, (float) $this->make(ServicioRepository::class)->find($limpieza)['precio_base']);
    $this->assertSame(5500.0, (float) $this->make(RepuestoRepository::class)->find($filtro)['precio']);
  }

  public function testUnAumentoQueSuperaElMaximoSeRechaza(): void
  {
    $this->crearServicio('Motor completo', 50000000);

    $this->assertValidationError(
      fn() => $this->make(PrecioService::class)->vistaPrevia(['aplicar_a' => 'servicios', 'porcentaje' => '100', 'redondeo' => '0']),
      'precio máximo',
    );
  }

  public function testFiltroPorProveedorYValidaciones(): void
  {
    $proveedor = $this->make(ProveedorService::class)->guardar(['nombre' => 'Distribuidora']);
    $this->make(RepuestoService::class)->guardar(['nombre' => 'Bujía', 'precio' => '1000', 'proveedor_id' => $proveedor]);
    $this->crearRepuesto('Correa', 2000);
    $precios = $this->make(PrecioService::class);

    $vista = $precios->vistaPrevia(['aplicar_a' => 'repuestos', 'porcentaje' => '-10', 'redondeo' => '0', 'proveedor_id' => $proveedor]);
    $this->assertSame(['Bujía'], array_column($vista['items'], 'nombre'));
    $this->assertSame(900.0, $vista['items'][0]['nuevo']);

    $this->expectException(ValidationException::class);
    $precios->vistaPrevia(['aplicar_a' => 'ambos', 'porcentaje' => '0', 'redondeo' => '0']);
  }

  public function testIngresoConCostoActualizaCostoYOpcionalmenteElPrecio(): void
  {
    $this->configurar('stock', ['margen_sugerido' => 50]);
    $filtro = $this->crearRepuesto('Filtro', 1000);
    $stock = $this->make(StockService::class);

    $stock->ingresar($filtro, '5', null, null, null, '800');
    $repuesto = $this->make(RepuestoRepository::class)->find($filtro);
    $this->assertSame(800.0, (float) $repuesto['precio_costo']);
    $this->assertSame(1000.0, (float) $repuesto['precio'], 'Sin tildar, el precio de venta no cambia');

    $stock->ingresar($filtro, '5', null, null, null, '900', true);
    $this->assertSame(1350.0, (float) $this->make(RepuestoRepository::class)->find($filtro)['precio']);
  }

  public function testCombos(): void
  {
    $limpieza = $this->crearServicio('Limpieza interna', 10000);
    $filtro = $this->crearRepuesto('Filtro', 5000);
    $pasta = $this->crearRepuesto('Pasta térmica (jeringa)', 3000);
    $combos = $this->make(ComboService::class);

    $id = $combos->guardar(['nombre' => 'Mantenimiento completo', 'items' => [
      'servicio' => [$limpieza => '1'],
      'repuesto' => [$filtro => '1', $pasta => '4'],
    ]]);

    $combo = $this->make(ComboRepository::class)->find($id);
    $this->assertCount(3, $combo['items']);
    $total = array_sum(array_map(fn($i) => (float) $i['precio'] * (float) $i['cantidad'], $combo['items']));
    $this->assertSame(27000.0, $total);

    try {
      $combos->guardar(['nombre' => 'Mantenimiento completo', 'items' => ['servicio' => [$limpieza => '1']]]);
      $this->fail('Nombre duplicado');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('Ya existe un combo', $e->getMessage());
    }

    $this->expectExceptionMessage('Agregá al menos un servicio o repuesto');
    $combos->guardar(['nombre' => 'Vacío', 'items' => []]);
  }
}
