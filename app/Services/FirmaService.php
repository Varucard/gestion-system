<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\FirmaRepository;
use App\Support\ImageUpload;
use App\Support\Validator;

/**
 * Conformidad del cliente firmada con el dedo en la pantalla (o con el mouse).
 *
 * El navegador manda el dibujo como PNG (data URL). Se guarda como cualquier imagen subida:
 * fuera de public/, re-codificada con GD (ImageUpload). Además se controla que no esté en
 * blanco: un toque sin querer no vale como firma.
 */
final class FirmaService
{
  public const MOMENTOS = ['recepcion' => 'al recibir el equipo', 'entrega' => 'al entregar el equipo'];

  /** Un dibujo de firma pesa unas decenas de KB: más que esto no es una firma. */
  private const MAX_BYTES = 512 * 1024;
  /**
   * Tamaño máximo del dibujo. El recuadro mide hasta 640×200 y en una pantalla densa se dibuja
   * al triple. Se controla antes de abrirlo: un PNG chico puede declarar 20000×20000 píxeles y
   * agotar la memoria del servidor al decodificarlo.
   */
  private const MAX_LADO = 2400;
  private const MAX_PIXELES = 2_500_000;
  /** Puntos con tinta que hacen falta (sobre una muestra de la imagen) para que cuente como firma. */
  private const TINTA_MINIMA = 40;
  private const LARGO_ACLARACION = 100;

  public function __construct(
    private readonly FirmaRepository $firmas,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, array<string, mixed>> momento => firma */
  public function deOrden(int $ordenId): array
  {
    return $this->firmas->deOrden($ordenId);
  }

  /** Guarda la firma (si ya había una de ese momento, la reemplaza). */
  public function guardar(int $ordenId, string $momento, string $dibujo, ?string $aclaracion, ?int $usuarioId): void
  {
    $aclaracion = Validator::nullable((string) $aclaracion);
    $binario = self::decodificar($dibujo);

    (new Validator())
      ->check(isset(self::MOMENTOS[$momento]), 'Firma inválida.')
      ->check($binario !== null && self::medidaValida($binario), 'La firma no llegó bien. Borrala y volvé a firmar.')
      ->check($aclaracion === null || mb_strlen($aclaracion) <= self::LARGO_ACLARACION, sprintf('La aclaración puede tener hasta %d caracteres.', self::LARGO_ACLARACION))
      ->validate();
    (new Validator())->check(self::tieneTinta($binario), 'La firma está en blanco: pedile al cliente que firme en el recuadro.')->validate();

    $tmp = tempnam(sys_get_temp_dir(), 'firma');
    try {
      file_put_contents($tmp, $binario);
      $archivo = ImageUpload::en('firmas')->guardar(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK]);
    } finally {
      @unlink($tmp);
    }

    // La firma es la prueba de conformidad: no se reemplaza (la de entrega se borra solo al
    // deshacer la entrega). Se controla con la orden bloqueada: dos envíos a la vez guardan uno.
    try {
      $this->firmas->transaction(function () use ($ordenId, $momento, $archivo, $aclaracion, $usuarioId) {
        (new Validator())
          ->check(!$this->firmas->existe($ordenId, $momento), 'El cliente ya firmó ' . self::MOMENTOS[$momento] . ' en esta orden.')
          ->validate();
        $this->firmas->guardar($ordenId, $momento, $archivo, $aclaracion, $usuarioId);
      });
    } catch (\Throwable $e) {
      ImageUpload::en('firmas')->eliminar($archivo);
      throw $e;
    }
    $this->auditor->registrar('firmar', 'orden', $ordenId, "Orden #{$ordenId}: el cliente firmó la conformidad " . self::MOMENTOS[$momento]);
  }

  public function eliminar(int $ordenId, string $momento): void
  {
    ImageUpload::en('firmas')->eliminar($this->firmas->eliminar($ordenId, $momento));
  }

  /** Archivo de la firma, para mostrarla. */
  public function ruta(int $ordenId, string $momento): string
  {
    $firma = $this->firmas->deOrden($ordenId)[$momento] ?? null;

    return ($firma ? ImageUpload::en('firmas')->ruta($firma['archivo']) : null) ?? throw new NotFoundException('Firma no encontrada.');
  }

  /** La firma como data URI, para los comprobantes (el PDF no carga imágenes por URL). */
  public function dataUri(int $ordenId, string $momento): ?string
  {
    try {
      $ruta = $this->ruta($ordenId, $momento);
    } catch (NotFoundException) {
      return null;
    }

    return 'data:image/png;base64,' . base64_encode((string) file_get_contents($ruta));
  }

  /** PNG de un data URL ("data:image/png;base64,…"), o null si no es eso. */
  public static function decodificar(string $dibujo): ?string
  {
    if (strlen($dibujo) > self::MAX_BYTES * 4 / 3 + 100 || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dibujo, $m)) {
      return null;
    }
    $binario = base64_decode($m[1], true);

    return $binario !== false && str_starts_with($binario, "\x89PNG") ? $binario : null;
  }

  /** ¿Es un PNG de un tamaño razonable para una firma? (Se mira la cabecera, sin decodificarlo.) */
  public static function medidaValida(string $png): bool
  {
    $info = @getimagesizefromstring($png);

    return $info !== false && $info[2] === IMAGETYPE_PNG && $info[0] >= 1 && $info[1] >= 1
      && max($info[0], $info[1]) <= self::MAX_LADO && $info[0] * $info[1] <= self::MAX_PIXELES;
  }

  /** ¿Hay trazos? Cuenta puntos opacos en una grilla sobre la imagen. */
  public static function tieneTinta(string $png): bool
  {
    $imagen = @imagecreatefromstring($png);
    if ($imagen === false) {
      throw new ValidationException(['La firma no llegó bien. Borrala y volvé a firmar.']);
    }

    [$ancho, $alto] = [imagesx($imagen), imagesy($imagen)];
    $paso = max(1, (int) floor(min($ancho, $alto) / 100));
    $tinta = 0;
    for ($x = 0; $x < $ancho; $x += $paso) {
      for ($y = 0; $y < $alto; $y += $paso) {
        $color = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));
        // Opaco (alpha 0 = opaco, 127 = transparente) y oscuro: un trazo sobre fondo vacío o blanco.
        if ($color['alpha'] < 64 && $color['red'] + $color['green'] + $color['blue'] < 600) {
          $tinta++;
        }
      }
    }

    return $tinta >= self::TINTA_MINIMA;
  }
}
