<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\EquipoRepository;
use App\Services\EquipoService;

final class EquipoTest extends IntegrationTestCase
{
  private function datos(int $cliente, array $mm, array $extra = []): array
  {
    return $extra + ['cliente_id' => $cliente, 'tipo' => 'notebook', 'marca_id' => $mm['marca'], 'modelo_id' => $mm['modelo']];
  }

  public function testGuardaLosDatosExtra(): void
  {
    $cliente = $this->crearCliente();
    $mm = $this->crearMarcaModelo();

    $id = $this->make(EquipoService::class)->crear($this->datos($cliente, $mm, [
      'numero_serie' => 'pf2 abc12', 'procesador' => 'Intel Core i5-1135G7', 'memoria' => '8 GB',
      'almacenamiento' => 'SSD 256 GB', 'color' => 'Gris', 'detalle' => 'Windows 11',
    ]));

    $e = $this->make(EquipoRepository::class)->find($id);
    $this->assertSame(
      ['notebook', 'PF2ABC12', 'Intel Core i5-1135G7', '8 GB', 'SSD 256 GB', 'Gris', 'Windows 11'],
      [$e['tipo'], $e['numero_serie'], $e['procesador'], $e['memoria'], $e['almacenamiento'], $e['color'], $e['detalle']]
    );
  }

  public function testElNumeroDeSerieEsOpcional(): void
  {
    $id = $this->make(EquipoService::class)->crear($this->datos($this->crearCliente(), $this->crearMarcaModelo()));

    $this->assertNull($this->make(EquipoRepository::class)->find($id)['numero_serie']);
  }

  public function testValidaTipoYNumeroDeSerie(): void
  {
    $cliente = $this->crearCliente();
    $mm = $this->crearMarcaModelo();

    try {
      $this->make(EquipoService::class)->crear($this->datos($cliente, $mm, ['tipo' => 'lavarropas', 'numero_serie' => 'A#']));
      $this->fail('Debía rechazar los datos');
    } catch (ValidationException $e) {
      $this->assertCount(2, $e->errors());
    }
  }

  public function testElNumeroDeSerieNoSeRepite(): void
  {
    $cliente = $this->crearCliente();
    $mm = $this->crearMarcaModelo();
    $service = $this->make(EquipoService::class);
    $service->crear($this->datos($cliente, $mm, ['numero_serie' => 'PF2ABC12']));

    $this->expectExceptionMessage('El número de serie ya corresponde a otro equipo');
    $service->crear($this->datos($cliente, $mm, ['numero_serie' => 'PF2ABC12']));
  }
}
