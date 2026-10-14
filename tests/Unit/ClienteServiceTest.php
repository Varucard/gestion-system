<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClienteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClienteServiceTest extends TestCase
{
  #[DataProvider('telefonos')]
  public function testNormalizaElTelefonoComoLoDictaLaGente(string $escrito, string $guardado): void
  {
    $this->assertSame($guardado, ClienteService::normalizarTelefono($escrito));
  }

  public static function telefonos(): array
  {
    return [
      'ya normalizado' => ['1123456789', '1123456789'],
      'con espacios y guión' => ['11 2345-6789', '1123456789'],
      'con 0 y 15 (CABA)' => ['011 15 2345-6789', '1123456789'],
      'con 15 sin 0' => ['11 15 2345 6789', '1123456789'],
      'con 0 y 15 (Córdoba)' => ['0351 15 555-1234', '3515551234'],
      'con 0 y 15 (área de 4)' => ['02954 15 412345', '2954412345'],
      'internacional' => ['+54 9 11 2345-6789', '1123456789'],
      'con 0, sin 15' => ['0351 555-1234', '3515551234'],
      'incompleto: queda para que la validación avise' => ['2345-6789', '23456789'],
    ];
  }

  public function testElCodigoDeAreaTieneQueExistir(): void
  {
    $this->assertTrue(ClienteService::telefonoValido('1123456789'));
    $this->assertTrue(ClienteService::telefonoValido('3515551234'));
    $this->assertTrue(ClienteService::telefonoValido('2954412345'));
    $this->assertFalse(ClienteService::telefonoValido(ClienteService::normalizarTelefono('15 2345 6789')), 'Celular dictado sin el 11');
    $this->assertFalse(ClienteService::telefonoValido('0123456789'));
  }
}
