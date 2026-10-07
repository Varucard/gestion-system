<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Support\ImageUpload;
use PHPUnit\Framework\TestCase;

final class ImageUploadTest extends TestCase
{
  private string $dir;

  protected function setUp(): void
  {
    $this->dir = sys_get_temp_dir() . '/uploads_' . bin2hex(random_bytes(4));
  }

  protected function tearDown(): void
  {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    @rmdir($this->dir);
  }

  /** @return array<string, mixed> como un elemento de $_FILES */
  private function archivo(string $contenido): array
  {
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmp, $contenido);

    return ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contenido)];
  }

  /** PNG mínimo que declara las dimensiones indicadas (sin datos reales de imagen). */
  private static function pngDeclarado(int $ancho, int $alto): string
  {
    $chunk = fn(string $tipo, string $datos) => pack('N', strlen($datos)) . $tipo . $datos . pack('N', crc32($tipo . $datos));

    return "\x89PNG\r\n\x1a\n"
      . $chunk('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0))
      . $chunk('IDAT', gzcompress(''))
      . $chunk('IEND', '');
  }

  public function testRechazaUnaImagenDeResolucionExageradaSinDecodificarla(): void
  {
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('resolución demasiado grande');

    (new ImageUpload($this->dir))->guardar($this->archivo(self::pngDeclarado(20000, 20000)));
  }

  public function testGuardaUnaImagenNormal(): void
  {
    $imagen = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($imagen);
    $jpg = (string) ob_get_clean();

    $nombre = (new ImageUpload($this->dir))->guardar($this->archivo($jpg));

    $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.jpg$/', $nombre);
    $this->assertSame([40, 30], array_slice(getimagesize("{$this->dir}/{$nombre}"), 0, 2));
  }
}
