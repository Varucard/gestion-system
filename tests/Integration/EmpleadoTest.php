<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Services\ClienteService;
use App\Services\EmpleadoService;
use App\Services\OrdenService;

final class EmpleadoTest extends IntegrationTestCase
{
  private function empleado(string $dni = '25111222'): int
  {
    return $this->make(EmpleadoService::class)->crear([
      'nombre' => 'Carlos', 'apellido' => 'Gómez', 'dni' => $dni, 'puesto' => 'Técnico', 'telefono' => '1144556677',
    ]);
  }

  public function testUnEmpleadoPuedeSerTambienCliente(): void
  {
    $this->empleado('25111222');
    $clientes = $this->make(ClienteService::class);

    $clienteId = $clientes->crear(['nombre' => 'Carlos', 'apellido' => 'Gómez', 'dni' => '25111222', 'telefono' => '1144556677']);

    $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM personas WHERE dni = '25111222'")->fetchColumn());

    // Al eliminar el cliente, la persona se conserva porque sigue siendo empleado.
    $clientes->eliminar($clienteId);
    $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM personas WHERE dni = '25111222'")->fetchColumn());
  }

  public function testNoSeRegistraDosVecesElMismoEmpleado(): void
  {
    $this->empleado('25111222');

    $this->expectExceptionMessage('ya está registrada como empleado');
    $this->empleado('25111222');
  }

  public function testOrdenConTecnicoAsignadoYTecnicoInactivo(): void
  {
    $tecnico = $this->empleado();
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Formateo', 100);
    $ordenes = $this->make(OrdenService::class);

    $id = $ordenes->guardar($equipo, [$servicio], [], null, $tecnico);
    $this->assertSame('GÓMEZ, CARLOS', $this->make(OrdenRepository::class)->find($id)['tecnico']);

    $this->make(EmpleadoService::class)->alternarEstado($tecnico);

    // La orden existente puede seguir con su técnico, pero no se asigna a una nueva.
    $ordenes->guardar($equipo, [$servicio], [], $id, $tecnico);
    $this->expectExceptionMessage('Seleccioná un técnico activo.');
    $ordenes->guardar($equipo, [$servicio], [], null, $tecnico);
  }
}
