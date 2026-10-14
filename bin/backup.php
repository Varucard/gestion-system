<?php

declare(strict_types=1);

/**
 * Claves y cifrado de los backups (ver BACKUP_CLAVE_PUBLICA en .env y "Backups" en el README).
 *
 *   php bin/backup.php claves                          genera un par de claves nuevo
 *   php bin/backup.php descifrar <archivo.cif> <salida> descifra (pide la clave privada)
 *   php bin/backup.php cifrar <archivo> <salida>        cifra con BACKUP_CLAVE_PUBLICA
 *
 * "-" en lugar de un archivo es la entrada o la salida estándar. La clave privada se lee de
 * BACKUP_CLAVE_PRIVADA si está en el entorno; si no, se pide por teclado (sin eco).
 *
 * Las claves se generan y los backups se descifran en una PC propia, NO en el servidor:
 * la clave privada no tiene que estar nunca ahí.
 */

use App\Core\Env;
use App\Support\CifradoBackup;

require dirname(__DIR__) . '/vendor/autoload.php';

[$comando, $origen, $destino] = array_pad(array_slice($argv, 1), 3, null);

function uso(): never
{
  fwrite(STDERR, "Uso:\n  php bin/backup.php claves\n  php bin/backup.php descifrar <archivo.cif> <salida>\n  php bin/backup.php cifrar <archivo> <salida>\n");
  exit(1);
}

function leerClavePrivada(): string
{
  $clave = (string) getenv('BACKUP_CLAVE_PRIVADA');
  if ($clave !== '') {
    return $clave;
  }
  $tty = @fopen('/dev/tty', 'r+');
  if ($tty === false) {
    fwrite(STDERR, "Falta la clave privada: pasala en BACKUP_CLAVE_PRIVADA.\n");
    exit(1);
  }
  fwrite($tty, 'Clave privada de backups: ');
  shell_exec('stty -echo < /dev/tty');
  $clave = trim((string) fgets($tty));
  shell_exec('stty echo < /dev/tty');
  fwrite($tty, PHP_EOL);

  return $clave;
}

try {
  switch ($comando) {
    case 'claves':
      $claves = CifradoBackup::generarClaves();
      echo "Clave pública (va en el .env del servidor, BACKUP_CLAVE_PUBLICA):\n{$claves['publica']}\n\n";
      echo "Clave privada (guardala FUERA del servidor, en un gestor de contraseñas; sin ella\n";
      echo "los backups no se pueden recuperar):\n{$claves['privada']}\n";
      break;
    case 'descifrar':
      ($origen && $destino) || uso();
      CifradoBackup::descifrar($origen, $destino, leerClavePrivada());
      break;
    case 'cifrar':
      ($origen && $destino) || uso();
      $publica = Env::get('BACKUP_CLAVE_PUBLICA') ?? throw new RuntimeException('Falta BACKUP_CLAVE_PUBLICA.');
      CifradoBackup::cifrar($origen, $destino, $publica);
      break;
    default:
      uso();
  }
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  if ($destino && $destino !== '-') {
    @unlink($destino);
  }
  exit(1);
}
