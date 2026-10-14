<?php

declare(strict_types=1);

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Código QR de un link (seguir el equipo, aceptar el presupuesto), para los tickets y los
 * comprobantes. Se arma en el servidor: no depende de internet ni de JavaScript.
 */
final class CodigoQr
{
  /** SVG para incrustar en una página (se imprime nítido en cualquier impresora). */
  public static function svg(string $texto): string
  {
    return (new QRCode(new QROptions([
      'outputType' => QROutputInterface::MARKUP_SVG,
      'outputBase64' => false,
      'svgAddXmlHeader' => false,
      'eccLevel' => EccLevel::M,
      'addQuietzone' => true,
      'quietzoneSize' => 2,
      'drawLightModules' => false,
      'svgUseFillAttributes' => true,
    ])))->render($texto);
  }

  /** PNG como data URI, para el PDF (Dompdf no lee bien SVG). */
  public static function pngDataUri(string $texto): string
  {
    return (new QRCode(new QROptions([
      'outputType' => QROutputInterface::GDIMAGE_PNG,
      'outputBase64' => true,
      'eccLevel' => EccLevel::M,
      'scale' => 6,
      'addQuietzone' => true,
      'quietzoneSize' => 2,
    ])))->render($texto);
  }
}
