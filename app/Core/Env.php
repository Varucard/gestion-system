<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lectura de variables de entorno.
 *
 * En Docker las variables llegan por `env_file`. Fuera de Docker (por ejemplo
 * con `php -S`) se cargan desde el archivo `.env` sin pisar las ya definidas.
 */
final class Env
{
  public static function load(string $file): void
  {
    if (!is_file($file) || !is_readable($file)) {
      return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
      }

      [$key, $value] = array_map('trim', explode('=', $line, 2));
      $value = trim($value, "\"'");

      if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
      }
    }
  }

  public static function get(string $key, ?string $default = null): ?string
  {
    $value = getenv($key);

    return $value === false || $value === '' ? $default : $value;
  }

  public static function bool(string $key, bool $default = false): bool
  {
    $value = self::get($key);

    return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
  }
}
