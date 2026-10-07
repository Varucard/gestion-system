<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HorarioAtencion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HorarioAtencionTest extends TestCase
{
  private HorarioAtencion $horario;

  protected function setUp(): void
  {
    $semana = ['desde' => '08:00', 'hasta' => '18:00'];
    // 2026-10-12 es lunes (feriado en este ejemplo).
    $this->horario = new HorarioAtencion(
      ['1' => $semana, '2' => $semana, '3' => $semana, '4' => $semana, '5' => $semana, '6' => ['desde' => '08:00', 'hasta' => '13:00'], '7' => null],
      ['2026-10-12'],
    );
  }

  public function testDiasHabilesYFeriados(): void
  {
    $this->assertTrue($this->horario->esDiaHabil('2026-10-09'));   // viernes
    $this->assertTrue($this->horario->esDiaHabil('2026-10-10'));   // sábado
    $this->assertFalse($this->horario->esDiaHabil('2026-10-11'));  // domingo
    $this->assertFalse($this->horario->esDiaHabil('2026-10-12'));  // feriado
    $this->assertTrue($this->horario->esFeriado('2026-10-12'));
  }

  public function testDentroDeHorarioNoIncluyeElCierre(): void
  {
    $this->assertTrue($this->horario->dentroDeHorario('2026-10-09', '08:00'));
    $this->assertTrue($this->horario->dentroDeHorario('2026-10-09', '17:30'));
    $this->assertFalse($this->horario->dentroDeHorario('2026-10-09', '18:00'));
    $this->assertFalse($this->horario->dentroDeHorario('2026-10-10', '14:00')); // sábado cierra 13
    $this->assertFalse($this->horario->dentroDeHorario('2026-10-11', '10:00'));
  }

  public function testSiguienteDiaHabilSaltaDomingoYFeriado(): void
  {
    $this->assertSame('2026-10-10', $this->horario->siguienteDiaHabil('2026-10-09'));
    $this->assertSame('2026-10-13', $this->horario->siguienteDiaHabil('2026-10-10'));
  }

  public function testAbiertoAhora(): void
  {
    $this->assertTrue($this->horario->abiertoAhora(new DateTimeImmutable('2026-10-09 10:15')));
    $this->assertFalse($this->horario->abiertoAhora(new DateTimeImmutable('2026-10-09 21:00')));
    $this->assertFalse($this->horario->abiertoAhora(new DateTimeImmutable('2026-10-12 10:00')));
  }

  public function testResumenAgrupaDiasConsecutivos(): void
  {
    $this->assertSame('Lunes a Viernes 08:00 a 18:00 · Sábado 08:00 a 13:00', $this->horario->resumen());
  }
}
