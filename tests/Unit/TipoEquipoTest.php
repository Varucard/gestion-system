<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TipoEquipo;
use PHPUnit\Framework\TestCase;

final class TipoEquipoTest extends TestCase
{
  public function testEtiquetaDelTipo(): void
  {
    $this->assertSame('PC de escritorio', TipoEquipo::etiqueta('pc'));
    $this->assertSame('', TipoEquipo::etiqueta('lavarropas'));
    $this->assertSame('', TipoEquipo::etiqueta(null));
  }

  public function testDescripcionDelEquipo(): void
  {
    $fila = ['tipo' => 'notebook', 'marca' => 'Lenovo', 'modelo' => 'IdeaPad 3', 'numero_serie' => 'PF2ABC12'];

    $this->assertSame('Notebook Lenovo IdeaPad 3 · S/N PF2ABC12', equipo_texto($fila));
    $this->assertSame('Notebook Lenovo IdeaPad 3', equipo_texto($fila, false));
    $this->assertSame('Notebook Lenovo IdeaPad 3', equipo_texto(['numero_serie' => null] + $fila));
  }
}
