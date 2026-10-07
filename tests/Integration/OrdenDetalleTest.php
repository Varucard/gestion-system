<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Services\OrdenService;
use App\Services\TurnoService;

final class OrdenDetalleTest extends IntegrationTestCase
{
  private int $cliente;
  private int $equipo;
  private int $servicio;

  protected function setUp(): void
  {
    parent::setUp();
    $this->cliente = $this->crearCliente();
    $this->equipo = $this->crearEquipo($this->cliente);
    $this->servicio = $this->crearServicio('Diagnóstico', 1000);
  }

  private function orden(array $detalle, ?int $id = null): int
  {
    return $this->make(OrdenService::class)->guardar($this->equipo, [$this->servicio], [], $id, null, $detalle);
  }

  public function testGuardaElDetalleDeIngresoYReparacion(): void
  {
    $id = $this->orden([
      'falla_reportada' => 'No enciende', 'accesorios' => 'Cargador original', 'estado_ingreso' => 'Tapa rayada',
      'diagnostico' => 'Pin de carga flojo', 'trabajo_realizado' => 'Cambio de pin de carga', 'notas_internas' => 'Cliente apurado',
      'proximo_mantenimiento_fecha' => date('Y-m-d', strtotime('+12 months')),
    ]);

    $orden = $this->make(OrdenRepository::class)->find($id);
    $this->assertSame(
      ['No enciende', 'Cargador original', 'Tapa rayada', 'Pin de carga flojo', 'Cambio de pin de carga', 'Cliente apurado'],
      [$orden['falla_reportada'], $orden['accesorios'], $orden['estado_ingreso'], $orden['diagnostico'], $orden['trabajo_realizado'], $orden['notas_internas']]
    );
    $this->assertSame(date('Y-m-d', strtotime('+12 months')), $orden['proximo_mantenimiento_fecha']);
  }

  public function testValidaAccesoriosYFechaDelProximoMantenimiento(): void
  {
    try {
      $this->orden(['accesorios' => str_repeat('x', 256), 'proximo_mantenimiento_fecha' => '2020-01-01']);
      $this->fail('Debía rechazar los datos');
    } catch (ValidationException $e) {
      $this->assertCount(2, $e->errors());
    }
  }

  public function testCambiarElProximoMantenimientoHabilitaDeNuevoElAviso(): void
  {
    $id = $this->orden(['proximo_mantenimiento_fecha' => date('Y-m-d', strtotime('+3 months'))]);
    $this->db->exec("UPDATE ordenes SET proximo_mantenimiento_avisado = NOW() WHERE id = {$id}");

    $this->orden(['proximo_mantenimiento_fecha' => date('Y-m-d', strtotime('+3 months'))], $id);
    $this->assertNotNull($this->make(OrdenRepository::class)->find($id)['proximo_mantenimiento_avisado'], 'Sin cambios se conserva');

    $this->orden(['proximo_mantenimiento_fecha' => date('Y-m-d', strtotime('+4 months'))], $id);
    $this->assertNull($this->make(OrdenRepository::class)->find($id)['proximo_mantenimiento_avisado']);
  }

  public function testOrdenDesdeUnTurnoLoMarcaComoRealizado(): void
  {
    $turno = $this->make(TurnoService::class)->guardar([
      'cliente_id' => $this->cliente, 'equipo_id' => $this->equipo, 'fecha' => date('Y-m-d', strtotime('+1 day')), 'hora' => '10:00',
    ]);

    $id = $this->orden(['turno_id' => $turno]);

    $this->assertSame($turno, (int) $this->make(OrdenRepository::class)->find($id)['turno_id']);
    $this->assertSame('realizado', $this->make(TurnoRepository::class)->find($turno)['estado']);
  }

  public function testElTurnoDebeSerDelMismoEquipo(): void
  {
    $otroEquipo = $this->crearEquipo($this->cliente, 'ZZZ999');
    $turno = $this->make(TurnoService::class)->guardar([
      'cliente_id' => $this->cliente, 'equipo_id' => $otroEquipo, 'fecha' => date('Y-m-d', strtotime('+1 day')), 'hora' => '10:00',
    ]);

    $this->expectExceptionMessage('El turno no corresponde al equipo de la orden.');
    $this->orden(['turno_id' => $turno]);
  }
}
