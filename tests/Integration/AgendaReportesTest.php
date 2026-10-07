<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ReporteRepository;
use App\Services\AgendaService;
use App\Services\EmpleadoService;
use App\Services\OrdenService;
use App\Services\PagoService;
use App\Services\TurnoService;
use App\Services\StockService;
use DateTimeImmutable;

final class AgendaReportesTest extends IntegrationTestCase
{
  public function testGrillaSemanalConCuposLibres(): void
  {
    $semana = ['desde' => '08:00', 'hasta' => '12:00'];
    $this->configurar('turnos', [
      'horario' => ['1' => $semana, '2' => $semana, '3' => $semana, '4' => $semana, '5' => $semana, '6' => null, '7' => null],
      'feriados' => [], 'cupos_por_horario' => 2, 'intervalo_minutos' => 60,
    ]);
    $lunes = (new DateTimeImmutable('monday next week'))->format('Y-m-d');
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $this->make(TurnoService::class)->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => $lunes, 'hora' => '09:00']);
    $this->make(TurnoService::class)->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => $lunes, 'hora' => '09:30']);

    $agenda = $this->make(AgendaService::class)->semana($lunes, new DateTimeImmutable('today'));

    $this->assertSame($lunes, $agenda['desde']);
    $this->assertSame(['08:00', '09:00', '10:00', '11:00'], $agenda['franjas']);
    $celda = $agenda['celdas'][$lunes]['09:00'];
    $this->assertCount(2, $celda['turnos'], 'El de 09:30 cae en la franja de 09:00');
    $this->assertSame(1, $celda['libres'], 'Solo el de las 09:00 ocupa ese horario exacto');
    $this->assertFalse($agenda['celdas'][$agenda['hasta']]['09:00']['abierta'], 'Domingo cerrado');
  }

  public function testUnaFechaInvalidaMuestraLaSemanaActual(): void
  {
    $hoy = new DateTimeImmutable('2026-10-07');
    foreach (['2026-13-45', '2026-02-30', 'cualquier cosa'] as $fecha) {
      $this->assertSame('2026-10-05', $this->make(AgendaService::class)->semana($fecha, $hoy)['desde'], $fecha);
    }
  }

  public function testReportes(): void
  {
    $tecnico = $this->make(EmpleadoService::class)->crear(['nombre' => 'Carlos', 'apellido' => 'Gómez', 'dni' => '25111222', 'puesto' => 'Técnico']);
    $equipo = $this->crearEquipo($this->crearCliente());
    $limpieza = $this->crearServicio('Limpieza interna', 1000);
    $filtro = $this->crearRepuesto('Filtro', 500);
    $this->make(StockService::class)->ingresar($filtro, '10', null, null, null, '300');
    $ordenes = $this->make(OrdenService::class);
    $hoy = date('Y-m-d');

    $a = $ordenes->guardar($equipo, [$limpieza], [$filtro => ['cantidad' => '2']], null, $tecnico);
    $b = $ordenes->guardar($equipo, [$limpieza], []);
    $c = $ordenes->guardar($equipo, [$limpieza], []);
    $ordenes->cambiarEstado($a, 'finalizado');
    $ordenes->cambiarEstado($c, 'cancelado');
    $this->make(PagoService::class)->registrar($a, ['monto' => '1500', 'forma_pago' => 'Contado'], null);
    $this->make(PagoService::class)->registrar($a, ['monto' => '500', 'forma_pago' => 'Mercado Pago'], null);
    // Un pago de una orden que después se canceló no cuenta como cobrado.
    $d = $ordenes->guardar($equipo, [$limpieza], []);
    $this->make(PagoService::class)->registrar($d, ['monto' => '300', 'forma_pago' => 'Contado'], null);
    $ordenes->cambiarEstado($d, 'cancelado');

    $reportes = $this->make(ReporteRepository::class);

    $cobranzas = $reportes->cobranzas($hoy, $hoy);
    $this->assertSame(['Contado', 'Mercado Pago'], array_column($cobranzas, 'forma_pago'));
    $this->assertSame('2000.00', number_format(array_sum(array_column($cobranzas, 'total')), 2, '.', ''));

    $servicios = $reportes->masVendidos('servicio', $hoy, $hoy);
    $this->assertSame(2, (int) $servicios[0]['ordenes'], 'La orden cancelada no cuenta');

    $tecnicos = $reportes->porTecnico($hoy, $hoy);
    $this->assertSame('GÓMEZ, CARLOS', $tecnicos[0]['tecnico']);
    $this->assertSame('Sin asignar', $tecnicos[1]['tecnico']);

    $stock = $reportes->stockValorizado();
    $this->assertSame(8.0, (float) $stock[0]['stock_actual']);
    $this->assertSame(2400.0, (float) $stock[0]['valor_costo']);
  }
}
