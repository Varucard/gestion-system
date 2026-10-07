<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Support\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
  public function testAcumulaTodosLosErrores(): void
  {
    $validator = (new Validator())
      ->check(false, 'primero')
      ->check(true, 'no aparece')
      ->check(false, 'segundo');

    try {
      $validator->validate();
      $this->fail('Debía lanzar ValidationException');
    } catch (ValidationException $e) {
      $this->assertSame(['primero', 'segundo'], $e->errors());
    }
  }

  public function testSinErroresNoLanza(): void
  {
    (new Validator())->check(true, 'ok')->validate();
    $this->addToAssertionCount(1);
  }

  #[DataProvider('fechas')]
  public function testFecha(string $valor, bool $esperado): void
  {
    $this->assertSame($esperado, Validator::fecha($valor));
  }

  public static function fechas(): array
  {
    return [
      'válida' => ['2026-10-02', true],
      'bisiesto' => ['2028-02-29', true],
      'día inexistente' => ['2026-02-30', false],
      'formato local' => ['02/10/2026', false],
      'vacía' => ['', false],
    ];
  }

  #[DataProvider('horas')]
  public function testHora(string $valor, bool $esperado): void
  {
    $this->assertSame($esperado, Validator::hora($valor));
  }

  public static function horas(): array
  {
    return [['09:30', true], ['23:59', true], ['10:00:00', true], ['24:00', false], ['9:30', false], ['ab:cd', false]];
  }

  public function testSoloLetrasAceptaAcentosYEnie(): void
  {
    $this->assertTrue(Validator::soloLetras('JOSÉ MARÍA', 2, 50));
    $this->assertTrue(Validator::soloLetras('MUÑOZ', 2, 50));
    $this->assertTrue(Validator::soloLetras("D'ANGELO", 2, 50));
    $this->assertFalse(Validator::soloLetras('JUAN2', 2, 50));
    $this->assertFalse(Validator::soloLetras('Ñ', 2, 50));
  }

  public function testImporteAceptaComaYRechazaNegativos(): void
  {
    $this->assertSame(1500.5, Validator::importe('1500,50'));
    $this->assertSame(0.0, Validator::importe('0'));
    $this->assertNull(Validator::importe('-1'));
    $this->assertNull(Validator::importe('abc'));
    $this->assertNull(Validator::importe(''));
    $this->assertNull(Validator::importe('1e9'), 'Sin notación científica');
    $this->assertNull(Validator::importe('1e400'));
    $this->assertNull(Validator::importe('+5'));
    $this->assertSame(99999999.99, Validator::importe('99999999.99'));
    $this->assertNull(Validator::importe('100000000'), 'Fuera del rango de la base');
  }

  public function testLargoCuentaCaracteresNoBytes(): void
  {
    $this->assertTrue(Validator::largo('ÑÑÑÑÑ', 5, 5));
  }
}
