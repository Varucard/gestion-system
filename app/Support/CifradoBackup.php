<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Cifrado de backups con una clave pública (libsodium).
 *
 * El servidor solo tiene la clave pública (BACKUP_CLAVE_PUBLICA): puede cifrar, pero no
 * descifrar. La privada queda fuera del servidor, en poder del dueño. Así, un backup que se
 * filtre (una copia en la nube, el disco de la VPS) no se puede leer sin ella.
 *
 * Formato del archivo .cif: "BKC1" + la clave del archivo sellada para la clave pública
 * (crypto_box_seal) + el encabezado del stream + bloques de [largo (4 bytes) + cifrado]
 * con XChaCha20-Poly1305. El último bloque va marcado: un archivo truncado no se descifra.
 */
final class CifradoBackup
{
  private const MAGICO = 'BKC1';
  private const BLOQUE = 65536;
  private const SELLO = 32 + SODIUM_CRYPTO_BOX_SEALBYTES;

  /** @return array{publica: string, privada: string} en base64 */
  public static function generarClaves(): array
  {
    $par = sodium_crypto_box_keypair();

    return ['publica' => base64_encode(sodium_crypto_box_publickey($par)), 'privada' => base64_encode($par)];
  }

  public static function cifrar(string $origen, string $destino, string $clavePublica): void
  {
    $publica = self::decodificar($clavePublica, SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, 'pública');
    [$entrada, $salida] = self::abrir($origen, $destino);

    try {
      $clave = sodium_crypto_secretstream_xchacha20poly1305_keygen();
      [$estado, $encabezado] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clave);
      fwrite($salida, self::MAGICO . sodium_crypto_box_seal($clave, $publica) . $encabezado);
      sodium_memzero($clave);

      $bloque = self::leer($entrada);
      do {
        $siguiente = feof($entrada) ? '' : self::leer($entrada);
        $etiqueta = $siguiente === '' ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
        $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push($estado, $bloque, '', $etiqueta);
        fwrite($salida, pack('N', strlen($cifrado)) . $cifrado);
        $bloque = $siguiente;
      } while ($etiqueta !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    } finally {
      fclose($entrada);
      fclose($salida);
    }
  }

  public static function descifrar(string $origen, string $destino, string $clavePrivada): void
  {
    $par = self::decodificar($clavePrivada, SODIUM_CRYPTO_BOX_KEYPAIRBYTES, 'privada');
    [$entrada, $salida] = self::abrir($origen, $destino);

    try {
      if (fread($entrada, 4) !== self::MAGICO) {
        throw new RuntimeException('El archivo no es un backup cifrado.');
      }
      $clave = sodium_crypto_box_seal_open(self::exacto($entrada, self::SELLO), $par);
      if ($clave === false) {
        throw new RuntimeException('La clave privada no corresponde a este backup.');
      }
      $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
        self::exacto($entrada, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),
        $clave,
      );
      sodium_memzero($clave);

      do {
        $largo = unpack('N', self::exacto($entrada, 4))[1];
        $resultado = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, self::exacto($entrada, $largo));
        if ($resultado === false) {
          throw new RuntimeException('El backup está dañado o fue modificado.');
        }
        [$plano, $etiqueta] = $resultado;
        fwrite($salida, $plano);
      } while ($etiqueta !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

      if (fread($entrada, 1) !== '') {
        throw new RuntimeException('El backup tiene datos de más después del final.');
      }
    } finally {
      fclose($entrada);
      fclose($salida);
    }
  }

  private static function decodificar(string $clave, int $largo, string $tipo): string
  {
    $bytes = base64_decode(trim($clave), true);
    if ($bytes === false || strlen($bytes) !== $largo) {
      throw new RuntimeException("La clave {$tipo} de backups no es válida.");
    }

    return $bytes;
  }

  /** @return array{0: resource, 1: resource} ("-" = entrada o salida estándar) */
  private static function abrir(string $origen, string $destino): array
  {
    $entrada = $origen === '-' ? fopen('php://stdin', 'rb') : @fopen($origen, 'rb');
    $salida = $destino === '-' ? fopen('php://stdout', 'wb') : @fopen($destino, 'wb');
    if ($entrada === false || $salida === false) {
      throw new RuntimeException("No se pudo abrir {$origen} o {$destino}.");
    }

    return [$entrada, $salida];
  }

  /** Lee un bloque completo (fread puede devolver menos en pipes). */
  private static function leer($entrada): string
  {
    $datos = '';
    while (strlen($datos) < self::BLOQUE && !feof($entrada)) {
      $datos .= (string) fread($entrada, self::BLOQUE - strlen($datos));
    }

    return $datos;
  }

  private static function exacto($entrada, int $largo): string
  {
    $datos = '';
    while (strlen($datos) < $largo && !feof($entrada)) {
      $datos .= (string) fread($entrada, $largo - strlen($datos));
    }
    if (strlen($datos) !== $largo) {
      throw new RuntimeException('El backup está incompleto.');
    }

    return $datos;
  }
}
