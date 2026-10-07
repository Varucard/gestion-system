<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
  public function testNumerosConComaDecimal(): void
  {
    $this->assertSame('1234,5', Csv::celda('1234.5'));
    $this->assertSame('-300', Csv::celda(-300), 'Un número negativo no es una fórmula');
    $this->assertSame('0012.5', Csv::celda('0012.5', esTexto: true), 'Las columnas de texto quedan como están');
  }

  public function testNeutralizaFormulas(): void
  {
    $this->assertSame("'=HYPERLINK(\"http://x\";\"ver\")", Csv::celda('=HYPERLINK("http://x";"ver")'));
    $this->assertSame("'+cmd|' /C calc'!A0", Csv::celda("+cmd|' /C calc'!A0"));
    $this->assertSame("'-2+3", Csv::celda('-2+3'));
    $this->assertSame("'@SUMA(A1)", Csv::celda('@SUMA(A1)'));
    $this->assertSame("'\t=1", Csv::celda("\t=1"));
    $this->assertSame("'=1", Csv::celda('=1', esTexto: true));
    $this->assertSame('Filtro de aceite', Csv::celda('Filtro de aceite'));
  }
}
