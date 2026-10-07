<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión: mensajes flash, datos del formulario anterior y token CSRF.
 */
final class Session
{
  private const FLASH = '_flash';
  private const OLD = '_old';
  private const CSRF = '_csrf';

  /** Datos flash de la petición anterior, ya retirados de la sesión. */
  private array $current = [];

  public function start(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      return;
    }

    session_start([
      'cookie_httponly' => true,
      'cookie_secure' => Request::esHttps(),
      'cookie_samesite' => 'Lax',
      'use_strict_mode' => true,
    ]);

    $this->current = [
      self::FLASH => $_SESSION[self::FLASH] ?? [],
      self::OLD => $_SESSION[self::OLD] ?? [],
    ];
    unset($_SESSION[self::FLASH], $_SESSION[self::OLD]);
  }

  /** Mensaje para mostrar en la próxima petición. Tipos: success | error. */
  public function flash(string $type, string $message): void
  {
    $_SESSION[self::FLASH][$type][] = $message;
  }

  /** @return array<string, list<string>> */
  public function messages(): array
  {
    return $this->current[self::FLASH] ?? [];
  }

  /** Guarda lo enviado en el formulario para volver a mostrarlo tras un error. */
  public function keepInput(array $input): void
  {
    unset($input['_token']);
    $_SESSION[self::OLD] = $input;
  }

  public function old(string $key, mixed $default = null): mixed
  {
    return $this->current[self::OLD][$key] ?? $default;
  }

  public function hasOldInput(): bool
  {
    return !empty($this->current[self::OLD]);
  }

  /** Ruta a la que se quería entrar antes de iniciar sesión (se consume al leerla). */
  public function pullIntended(string $default = '/'): string
  {
    $path = $_SESSION['_intended'] ?? $default;
    unset($_SESSION['_intended']);

    // Solo rutas internas: evita redirecciones abiertas.
    return is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : $default;
  }

  public function csrfToken(): string
  {
    if (empty($_SESSION[self::CSRF])) {
      $_SESSION[self::CSRF] = bin2hex(random_bytes(32));
    }

    return $_SESSION[self::CSRF];
  }

  public function validCsrf(?string $token): bool
  {
    return is_string($token) && hash_equals($this->csrfToken(), $token);
  }
}
