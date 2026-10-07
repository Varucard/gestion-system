<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;
use RuntimeException;

/**
 * Guarda imágenes subidas fuera de la carpeta pública.
 *
 * La imagen se re-codifica con GD (máx. 1600 px): así se descartan metadatos
 * (ubicación GPS, etc.) y cualquier contenido que no sea una imagen real.
 */
final class ImageUpload
{
  public const MAX_BYTES = 8 * 1024 * 1024;
  private const MAX_LADO = 1600;
  /**
   * Máximo de píxeles a decodificar (~40 MP, más que una foto de celular normal). GD usa
   * unos 4-5 bytes por píxel: sin este límite, un PNG chico pero de 20000×20000 px agota la
   * memoria del servidor.
   */
  private const MAX_PIXELES = 40_000_000;
  private const TIPOS = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

  public function __construct(private readonly string $directorio)
  {
  }

  /** Directorio storage/uploads/{$carpeta} de la aplicación. */
  public static function en(string $carpeta): self
  {
    return new self(\App\Core\App::instance()->rootPath . '/storage/uploads/' . $carpeta);
  }

  /**
   * @param array<string, mixed> $file elemento de $_FILES
   * @return string nombre del archivo guardado
   */
  public function guardar(array $file): string
  {
    $errorSubida = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    (new Validator())
      ->check($errorSubida !== UPLOAD_ERR_INI_SIZE && $errorSubida !== UPLOAD_ERR_FORM_SIZE, 'La imagen supera el tamaño máximo de 8 MB.')
      ->check(in_array($errorSubida, [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true), 'No se pudo subir la imagen. Intentá nuevamente.')
      ->validate();

    $tmp = (string) $file['tmp_name'];
    $info = is_file($tmp) && filesize($tmp) <= self::MAX_BYTES ? @getimagesize($tmp) : false;
    $tipo = $info[2] ?? null;

    if ($info === false || !isset(self::TIPOS[$tipo])) {
      throw new ValidationException(['El archivo debe ser una imagen JPG, PNG o WEBP de hasta 8 MB.']);
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELES) {
      throw new ValidationException(['La imagen tiene una resolución demasiado grande. Achicala o sacala con menos megapíxeles.']);
    }

    $origen = match ($tipo) {
      IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
      IMAGETYPE_PNG => @imagecreatefrompng($tmp),
      IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
    };
    if ($origen === false) {
      throw new ValidationException(['La imagen está dañada o no se puede leer.']);
    }

    [$ancho, $alto] = [imagesx($origen), imagesy($origen)];
    $escala = min(1, self::MAX_LADO / max($ancho, $alto));
    $imagen = imagescale($origen, max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)));

    if (!is_dir($this->directorio) && !mkdir($this->directorio, 0775, true) && !is_dir($this->directorio)) {
      throw new RuntimeException("No se pudo crear {$this->directorio}.");
    }

    // Todo se guarda como JPEG (fotos de equipos y personas), salvo PNG con transparencia.
    $conTransparencia = $tipo === IMAGETYPE_PNG;
    $nombre = bin2hex(random_bytes(16)) . ($conTransparencia ? '.png' : '.jpg');
    $destino = $this->directorio . '/' . $nombre;

    if ($conTransparencia) {
      imagesavealpha($imagen, true);
      $ok = imagepng($imagen, $destino, 8);
    } else {
      $ok = imagejpeg($imagen, $destino, 85);
    }

    if (!$ok) {
      throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return $nombre;
  }

  public function ruta(string $nombre): ?string
  {
    // Solo nombres generados por guardar(): evita leer archivos fuera del directorio.
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $nombre)) {
      return null;
    }

    $ruta = $this->directorio . '/' . $nombre;

    return is_file($ruta) ? $ruta : null;
  }

  public function eliminar(?string $nombre): void
  {
    if ($nombre !== null && ($ruta = $this->ruta($nombre)) !== null) {
      @unlink($ruta);
    }
  }

  /** Envía la imagen al navegador. */
  public static function enviar(string $ruta): void
  {
    header('Content-Type: ' . (str_ends_with($ruta, '.png') ? 'image/png' : 'image/jpeg'));
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
  }
}
