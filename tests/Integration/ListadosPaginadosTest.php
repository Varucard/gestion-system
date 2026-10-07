<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ClienteRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Services\ClienteService;
use App\Services\OrdenService;
use App\Services\PagoService;
use App\Services\TurnoService;

final class ListadosPaginadosTest extends IntegrationTestCase
{
  public function testOrdenesConFiltrosYBusqueda(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $servicio = $this->crearServicio('Formateo', 1000);
    $ordenes = $this->make(OrdenService::class);
    $ids = [];
    for ($i = 0; $i < 5; $i++) {
      $ids[] = $ordenes->guardar($equipo, [$servicio], []);
    }
    $ordenes->cambiarEstado($ids[0], 'finalizado');
    $ordenes->cambiarEstado($ids[1], 'cancelado');
    $this->make(PagoService::class)->registrar($ids[2], ['monto' => '1000', 'forma_pago' => 'Contado'], null);
    $repo = $this->make(OrdenRepository::class);

    $this->assertSame(5, $repo->paginar([])['recordsTotal']);
    $this->assertSame(3, $repo->paginar(['estado' => 'abiertas'])['recordsTotal']);
    $this->assertSame(3, $repo->paginar(['estado' => 'con_saldo'])['recordsTotal'], 'Pagada y cancelada no tienen saldo');
    $this->assertSame(1, $repo->paginar(['estado' => 'finalizado'])['recordsTotal']);
    $this->assertSame(5, $repo->paginar(['search' => ['value' => 'ab123cd pérez']])['recordsFiltered']);

    $pagina = $repo->paginar(['length' => '2', 'start' => '2', 'order' => [['column' => '0', 'dir' => 'asc']]]);
    $this->assertSame([$ids[2], $ids[3]], array_map('intval', array_column($pagina['filas'], 'id')));
  }

  public function testClientesYTurnos(): void
  {
    $clientes = $this->make(ClienteService::class);
    $a = $clientes->crear(['nombre' => 'Ana', 'apellido' => 'Zapata', 'dni' => '20111222', 'telefono' => '1111111111']);
    $clientes->crear(['nombre' => 'Bruno', 'apellido' => 'Alvarez', 'dni' => '20111333', 'telefono' => '2222222222']);
    $clientes->alternarEstado($a);

    $repo = $this->make(ClienteRepository::class);
    $this->assertSame(['ALVAREZ', 'ZAPATA'], array_column($repo->paginar([])['filas'], 'apellido'));
    $this->assertSame(1, $repo->paginar(['estado' => 'inactivo'])['recordsTotal']);
    $this->assertSame(1, $repo->paginar(['search' => ['value' => '20111333']])['recordsFiltered']);

    $equipo = $this->crearEquipo($a);
    $turnos = $this->make(TurnoService::class);
    $turnos->guardar(['cliente_id' => $a, 'equipo_id' => $equipo, 'fecha' => date('Y-m-d', strtotime('+2 days')), 'hora' => '10:00']);
    $this->db->exec("INSERT INTO turnos (cliente_id, equipo_id, fecha, hora, estado) VALUES ({$a}, {$equipo}, CURDATE() - INTERVAL 5 DAY, '10:00:00', 'realizado')");

    $turnosRepo = $this->make(TurnoRepository::class);
    $this->assertSame(1, $turnosRepo->paginar(['periodo' => 'proximos'])['recordsTotal']);
    $this->assertSame(1, $turnosRepo->paginar(['periodo' => 'pasados', 'estado' => 'realizado'])['recordsTotal']);
  }
}
