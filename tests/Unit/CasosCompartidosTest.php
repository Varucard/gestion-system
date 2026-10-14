<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClienteService;
use App\Services\EquipoService;
use App\Support\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas del servidor contra los casos de tests/casos/, los mismos que prueba
 * tests/js/reglas.test.mjs contra el navegador: así las dos copias no se separan.
 */
final class CasosCompartidosTest extends TestCase
{
  /** @return iterable<string, array{0: string, 1: mixed}> */
  private static function casos(string $archivo, string $grupo): iterable
  {
    $datos = json_decode((string) file_get_contents(dirname(__DIR__) . "/casos/{$archivo}.json"), true, flags: JSON_THROW_ON_ERROR);
    foreach ($datos[$grupo] as $caso) {
      yield ($caso['nota'] ?? '') !== '' ? "{$caso['entrada']} ({$caso['nota']})" : "«{$caso['entrada']}»" => [$caso['entrada'], $caso['esperado']];
    }
  }

  public static function importes(): iterable
  {
    return self::casos('importes', 'importe');
  }

  public static function cantidadesAmbiguas(): iterable
  {
    return self::casos('importes', 'cantidadAmbigua');
  }

  public static function telefonos(): iterable
  {
    return self::casos('telefonos', 'normalizar');
  }

  public static function telefonosValidos(): iterable
  {
    return self::casos('telefonos', 'valido');
  }

  public static function series(): iterable
  {
    return self::casos('series', 'normalizar');
  }

  public static function seriesValidas(): iterable
  {
    return self::casos('series', 'valida');
  }

  #[DataProvider('importes')]
  public function testImporte(string $entrada, int|float|null $esperado): void
  {
    $this->assertSame($esperado === null ? null : (float) $esperado, Validator::importe($entrada));
  }

  #[DataProvider('cantidadesAmbiguas')]
  public function testCantidadAmbigua(string $entrada, bool $ambigua): void
  {
    // Validator::cantidad() rechaza la cantidad ambigua; si no lo es, la lee como un importe.
    $ambigua ? $this->assertNull(Validator::cantidad($entrada)) : $this->assertSame(Validator::importe($entrada), Validator::cantidad($entrada));
  }

  #[DataProvider('telefonos')]
  public function testNormalizarTelefono(string $entrada, string $esperado): void
  {
    $this->assertSame($esperado, ClienteService::normalizarTelefono($entrada));
  }

  #[DataProvider('telefonosValidos')]
  public function testTelefonoValido(string $entrada, bool $esperado): void
  {
    $this->assertSame($esperado, ClienteService::telefonoValido($entrada));
  }

  #[DataProvider('series')]
  public function testNormalizarSerie(string $entrada, string $esperado): void
  {
    $this->assertSame($esperado, EquipoService::normalizarSerie($entrada));
  }

  #[DataProvider('seriesValidas')]
  public function testSerieValida(string $entrada, bool $esperado): void
  {
    $this->assertSame($esperado, EquipoService::serieValida($entrada));
  }
}
