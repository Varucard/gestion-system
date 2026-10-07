<?php

declare(strict_types=1);

/**
 * Aplica las migraciones pendientes de la base de datos.
 *
 *   php bin/migrate.php
 *
 * Se ejecuta automáticamente al iniciar el contenedor `public`.
 */

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$intentos = (int) ($argv[1] ?? 1);
for ($i = 1; ; $i++) {
  try {
    $db = Database::connect();
    break;
  } catch (PDOException $e) {
    if ($i >= $intentos) {
      fwrite(STDERR, "No se pudo conectar a la base: {$e->getMessage()}\n");
      exit(1);
    }
    sleep(2);
  }
}

try {
  $aplicadas = (new Migrator($db, dirname(__DIR__) . '/database/migrations'))
    ->migrate(fn(string $msg) => print($msg . PHP_EOL));
} catch (RuntimeException $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}

echo $aplicadas === [] ? "La base ya está actualizada.\n" : count($aplicadas) . " migración(es) aplicada(s).\n";
