<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Services\OrdenService;
use App\Services\PortalService;

final class PortalTest extends IntegrationTestCase
{
  /** Cliente con una orden; devuelve el número de esa orden. */
  private function preparar(): int
  {
    $equipo = $this->crearEquipo($this->crearCliente('30111222'), 'AB123CD');

    return $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio('Formateo', 1000)], []);
  }

  public function testConDniYNumeroDeOrdenMuestraElHistorial(): void
  {
    $orden = $this->preparar();

    $resultado = $this->make(PortalService::class)->consultar('30.111.222', "#{$orden}", '10.0.0.1');

    $this->assertSame('Juan', $resultado['nombre']);
    $this->assertCount(1, $resultado['ordenes']);
    $this->assertSame('pendiente', $resultado['ordenes'][0]['estado']);
    $this->assertArrayNotHasKey('telefono', $resultado, 'No expone datos de contacto');
  }

  public function testSiElEquipoCambiaDeDuenioElNuevoNoVeLasOrdenesDelAnterior(): void
  {
    $anterior = $this->crearCliente('30111222');
    $equipo = $this->crearEquipo($anterior, 'AB123CD');
    $ordenes = $this->make(OrdenService::class);
    $vieja = $ordenes->guardar($equipo, [$this->crearServicio('Formateo', 1000)], []);
    $ordenes->cambiarEstado($vieja, 'finalizado');

    // Se vende el equipo: pasa a otro cliente.
    $nuevo = $this->crearCliente('40222333');
    $this->db->exec("UPDATE equipos SET cliente_id = {$nuevo} WHERE id = {$equipo}");
    $portal = $this->make(PortalService::class);

    $this->assertSame([], $this->make(OrdenRepository::class)->porCliente($nuevo));
    $this->assertCount(1, $this->make(OrdenRepository::class)->porCliente($anterior), 'El historial sigue siendo del dueño anterior');
    $this->assertSame([$anterior => 1000.0], $this->make(PagoRepository::class)->saldosDe([$anterior, $nuevo]), 'La deuda también');

    // El nuevo dueño no puede entrar con el número de una orden del anterior.
    try {
      $portal->consultar('40222333', (string) $vieja, '10.0.0.1');
      $this->fail('Debía rechazar la consulta');
    } catch (ValidationException) {
    }

    // Una orden nueva del mismo equipo ya es del dueño actual, y solo ve esa.
    $propia = $ordenes->guardar($equipo, [$this->crearServicio('Limpieza', 500)], []);
    $this->assertCount(1, $portal->consultar('40222333', (string) $propia, '10.0.0.1')['ordenes']);
  }

  public function testOrdenAjenaODniInexistenteDanElMismoError(): void
  {
    $orden = $this->preparar();
    $ajena = $this->make(OrdenService::class)->guardar(
      $this->crearEquipo($this->crearCliente('40222333'), 'ZZZ999'), [$this->crearServicio('Limpieza', 500)], []
    );
    $portal = $this->make(PortalService::class);

    foreach ([['30111222', (string) $ajena], ['99999999', (string) $orden]] as [$dni, $numero]) {
      try {
        $portal->consultar($dni, $numero, '10.0.0.2');
        $this->fail('Debía rechazar la consulta');
      } catch (ValidationException $e) {
        $this->assertStringContainsString('No encontramos datos con esa combinación', $e->getMessage());
      }
    }
  }

  public function testSoloDniSiLaConfiguracionLoPermite(): void
  {
    $this->preparar();
    $this->configurar('portal', ['requiere_orden' => false]);

    $this->assertSame('Juan', $this->make(PortalService::class)->consultar('30111222', '', '10.0.0.3')['nombre']);
  }

  public function testBloqueaTrasDemasiadosIntentos(): void
  {
    $orden = $this->preparar();
    $portal = $this->make(PortalService::class);

    for ($i = 0; $i < PortalService::MAX_INTENTOS; $i++) {
      try {
        $portal->consultar('30111222', '999999', '10.0.0.4');
      } catch (ValidationException) {
      }
    }

    $this->expectExceptionMessage('Demasiadas consultas fallidas');
    $portal->consultar('30111222', (string) $orden, '10.0.0.4');
  }

  public function testDesdeElPresupuestoEntraSinDniNiNumeroDeOrden(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente('30111222'), 'AB123CD');
    $id = $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio('Formateo', 1000)], []);
    $portal = $this->make(PortalService::class);

    $resultado = $portal->consultarPorOrden($id);
    $this->assertSame('Juan', $resultado['nombre']);
    $this->assertSame($id, (int) $resultado['ordenes'][0]['id']);

    $this->configurar('portal', ['habilitado' => false]);
    $this->expectException(NotFoundException::class);
    $this->make(PortalService::class)->consultarPorOrden($id);
  }

  public function testDeshabilitado(): void
  {
    $this->configurar('portal', ['habilitado' => false]);

    $this->expectException(NotFoundException::class);
    $this->make(PortalService::class)->consultar('30111222', '1', '10.0.0.5');
  }
}
