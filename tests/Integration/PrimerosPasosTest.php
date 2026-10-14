<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Rol;
use App\Repositories\MarcaRepository;
use App\Services\PrimerosPasosService;

/** Primeros pasos al empezar a usar el sistema. */
final class PrimerosPasosTest extends HttpTestCase
{
  /** @return array<string, bool> clave => hecho */
  private function estado(): array
  {
    return array_column($this->make(PrimerosPasosService::class)->pasos(), 'hecho', 'clave');
  }

  public function testSeTildanSolosAMedidaQueSeCarga(): void
  {
    $this->assertFalse($this->estado()['servicios']);
    $this->assertTrue($this->make(PrimerosPasosService::class)->mostrarEnElInicio());

    $this->crearServicio('Formateo', 20000);
    $this->configurar('negocio', ['nombre' => 'Mi negocio']);

    $this->assertTrue($this->estado()['servicios']);
    $this->assertTrue($this->estado()['negocio']);
  }

  public function testMarcasComunesDeUnClicSinDuplicar(): void
  {
    $this->crearMarcaModelo('Lenovo', 'IdeaPad 3');

    $agregados = $this->make(PrimerosPasosService::class)->cargarMarcasComunes();

    $this->assertGreaterThan(50, $agregados);
    $this->assertSame(0, $this->make(PrimerosPasosService::class)->cargarMarcasComunes(), 'la segunda vez no agrega nada');
    $this->assertCount(1, array_filter($this->make(MarcaRepository::class)->all(), fn($m) => mb_strtolower($m['nombre']) === 'lenovo'));
  }

  public function testElAdministradorLosVeEnElInicioHastaOcultarlos(): void
  {
    $this->ingresarComo(Rol::Administrador);

    $this->assertStringContainsString('Primeros pasos', $this->pedir('GET', '/')->html());
    $this->assertStringContainsString('Cargar las marcas más comunes', $this->pedir('GET', '/primeros-pasos')->html());

    $this->pedir('POST', '/primeros-pasos/ocultar');
    $this->assertFalse($this->make(PrimerosPasosService::class)->mostrarEnElInicio());
    $this->assertStringNotContainsString('primeros-pasos-aviso', $this->pedir('GET', '/')->html());
  }

  public function testUnEmpleadoNoLosVe(): void
  {
    $this->ingresarComo(Rol::Empleado);

    $this->assertStringNotContainsString('primeros-pasos-aviso', $this->pedir('GET', '/')->html());
  }
}
