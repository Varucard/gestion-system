<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Estado;
use App\Enums\EstadoOrden;
use App\Enums\EstadoTurno;
use App\Models\Cliente;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Persona;
use App\Services\OrdenService;
use PHPUnit\Framework\TestCase;

final class DominioTest extends TestCase
{
  public function testTotalDeLaOrdenSumaServiciosYRepuestos(): void
  {
    $orden = new Orden(1, [
      OrdenItem::servicio(1, 15000.50),
      OrdenItem::servicio(2, 8000.10),
      OrdenItem::repuesto(1, 4500.20),
    ]);

    $this->assertSame(27500.8, $orden->total());
    $this->assertTrue($orden->items[2]->esRepuesto());
    $this->assertNull($orden->items[2]->servicioId);
  }

  public function testElSubtotalMultiplicaCantidadPorPrecio(): void
  {
    $orden = new Orden(1, [
      OrdenItem::servicio(1, 1000, 2),
      OrdenItem::repuesto(1, 2500.50, 1.5),
    ]);

    $this->assertSame(3750.75, $orden->items[1]->subtotal());
    $this->assertSame(5750.75, $orden->total());
  }

  public function testSoloSeEditanOrdenesAbiertas(): void
  {
    $this->assertTrue(OrdenService::editable(EstadoOrden::Pendiente));
    $this->assertTrue(OrdenService::editable(EstadoOrden::EnProceso));
    $this->assertFalse(OrdenService::editable(EstadoOrden::Finalizado));
    $this->assertFalse(OrdenService::editable(EstadoOrden::Cancelado));
  }

  public function testTurnosCanceladosLiberanElHorario(): void
  {
    $this->assertTrue(EstadoTurno::Cancelado->liberaHorario());
    $this->assertTrue(EstadoTurno::NoAsistio->liberaHorario());
    $this->assertFalse(EstadoTurno::Pendiente->liberaHorario());
    $this->assertFalse(EstadoTurno::Confirmado->liberaHorario());
  }

  public function testAlternarEstado(): void
  {
    $this->assertSame(Estado::Inactivo, Estado::Activo->alternar());
    $this->assertSame(Estado::Activo, Estado::Inactivo->alternar());
  }

  public function testClienteHeredaDePersona(): void
  {
    $cliente = new Cliente('JUAN', 'PÉREZ', '30111222', '1122334455');

    $this->assertInstanceOf(Persona::class, $cliente);
    $this->assertSame('PÉREZ, JUAN', $cliente->nombreCompleto());
    $this->assertSame(Estado::Activo, $cliente->estado);
  }
}
