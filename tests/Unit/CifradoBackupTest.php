<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CifradoBackup;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CifradoBackupTest extends TestCase
{
  private string $dir;

  protected function setUp(): void
  {
    $this->dir = sys_get_temp_dir() . '/cifrado_' . bin2hex(random_bytes(4));
    mkdir($this->dir);
  }

  protected function tearDown(): void
  {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    rmdir($this->dir);
  }

  /** @return array{0: string, 1: string} rutas del original y del cifrado */
  private function cifrar(string $contenido, string $publica): array
  {
    file_put_contents("{$this->dir}/plano", $contenido);
    CifradoBackup::cifrar("{$this->dir}/plano", "{$this->dir}/plano.cif", $publica);

    return ["{$this->dir}/plano", "{$this->dir}/plano.cif"];
  }

  public function testIdaYVueltaConVariosBloques(): void
  {
    $claves = CifradoBackup::generarClaves();
    foreach (['', 'corto', random_bytes(65536), random_bytes(65536 * 3 + 17)] as $contenido) {
      [, $cifrado] = $this->cifrar($contenido, $claves['publica']);
      $this->assertStringNotContainsString('corto', (string) file_get_contents($cifrado));

      CifradoBackup::descifrar($cifrado, "{$this->dir}/vuelta", $claves['privada']);
      $this->assertSame($contenido, file_get_contents("{$this->dir}/vuelta"));
    }
  }

  public function testOtraClaveNoDescifra(): void
  {
    [, $cifrado] = $this->cifrar('secreto', CifradoBackup::generarClaves()['publica']);

    $this->expectException(RuntimeException::class);
    CifradoBackup::descifrar($cifrado, "{$this->dir}/vuelta", CifradoBackup::generarClaves()['privada']);
  }

  public function testDetectaUnArchivoModificadoOTruncado(): void
  {
    $claves = CifradoBackup::generarClaves();
    [, $cifrado] = $this->cifrar(random_bytes(100000), $claves['publica']);
    $original = (string) file_get_contents($cifrado);

    foreach ([substr($original, 0, -10), substr_replace($original, 'X', 500, 1)] as $danado) {
      file_put_contents($cifrado, $danado);
      try {
        CifradoBackup::descifrar($cifrado, "{$this->dir}/vuelta", $claves['privada']);
        $this->fail('Tenía que rechazar el archivo dañado');
      } catch (RuntimeException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  public function testRechazaUnaClaveMalFormada(): void
  {
    $this->expectException(RuntimeException::class);
    $this->cifrar('x', 'no-es-una-clave');
  }
}
