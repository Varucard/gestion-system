<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Fábrica de la conexión PDO a MySQL a partir de variables de entorno.
 */
final class Database
{
  public static function connect(): PDO
  {
    $host = Env::get('DB_HOST', 'database');
    $port = Env::get('DB_PORT', '3306');
    $name = Env::get('DB_DATABASE', 'servicio_tecnico');

    $pdo = new PDO(
      "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
      Env::get('DB_USERNAME', 'root'),
      Env::get('DB_PASSWORD', ''),
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );

    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
  }
}
