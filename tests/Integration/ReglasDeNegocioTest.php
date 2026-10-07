<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\OrdenRepository;
use App\Services\ClienteService;
use App\Services\MarcaService;
use App\Services\OrdenService;
use App\Services\TurnoService;

final class ReglasDeNegocioTest extends IntegrationTestCase
{
  public function testNoSePermitenDnisDuplicados(): void
  {
    $this->crearCliente('30111222');

    $this->expectExceptionMessage('Ya existe un cliente con ese DNI');
    $this->crearCliente('30111222');
  }

  public function testUnClienteConEquiposNoSeElimina(): void
  {
    $cliente = $this->crearCliente();
    $this->crearEquipo($cliente);

    $this->expectException(ValidationException::class);
    $this->make(ClienteService::class)->eliminar($cliente);
  }

  public function testUnaMarcaEnUsoNoSeElimina(): void
  {
    $mm = $this->crearMarcaModelo();

    $this->expectExceptionMessage('no se puede eliminar');
    $this->make(MarcaService::class)->eliminar($mm['marca']);
  }

  public function testLosPreciosDeLaOrdenQuedanCongelados(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $limpieza = $this->crearServicio('Limpieza', 1000);
    $formateo = $this->crearServicio('Formateo', 500);
    $filtro = $this->crearRepuesto('Filtro', 300);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar($equipo, [$limpieza], [$filtro]);
    $this->assertSame('1300.00', $this->make(OrdenRepository::class)->find($id)['total']);

    // Sube el precio del catálogo y se agrega otro servicio: la limpieza conserva su costo.
    $this->db->exec("UPDATE servicios SET precio_base = 9999 WHERE id = {$limpieza}");
    $ordenes->guardar($equipo, [$limpieza, $formateo], [$filtro], $id);

    $this->assertSame('1800.00', $this->make(OrdenRepository::class)->find($id)['total']);
  }

  public function testUnaOrdenFinalizadaNoSeEdita(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Limpieza', 1000);
    $ordenes = $this->make(OrdenService::class);
    $id = $ordenes->guardar($equipo, [$servicio], []);
    $ordenes->cambiarEstado($id, 'finalizado');

    $this->expectExceptionMessage('Solo se pueden editar órdenes pendientes o en proceso');
    $ordenes->guardar($equipo, [$servicio], [], $id);
  }

  public function testNoSeSuperponenTurnosSalvoCancelados(): void
  {
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $turnos = $this->make(TurnoService::class);
    $datos = ['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => date('Y-m-d', strtotime('+1 day')), 'hora' => '10:00'];

    $primero = $turnos->guardar($datos);

    try {
      $turnos->guardar($datos);
      $this->fail('Debía rechazar el horario ocupado');
    } catch (ValidationException) {
    }

    $turnos->cambiarEstado($primero, 'cancelado');
    $this->assertGreaterThan($primero, $turnos->guardar($datos));
  }

  public function testElEquipoDelTurnoDebeSerDelCliente(): void
  {
    $equipoAjeno = $this->crearEquipo($this->crearCliente('20111222'));
    $cliente = $this->crearCliente('30111222');

    $this->expectExceptionMessage('El equipo no pertenece al cliente');
    $this->make(TurnoService::class)->guardar([
      'cliente_id' => $cliente, 'equipo_id' => $equipoAjeno, 'fecha' => date('Y-m-d', strtotime('+1 day')), 'hora' => '10:00',
    ]);
  }
}
