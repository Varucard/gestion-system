<?php

declare(strict_types=1);

namespace App\Core;

use App\Enums\Rol;
use App\Repositories\UsuarioRepository;

/**
 * Usuario autenticado en la sesión actual.
 */
final class Auth
{
  private const KEY = '_auth';
  private const INACTIVIDAD_MAXIMA = 8 * 3600;

  /** Id del usuario ya verificado contra la base en este request. */
  private ?int $verificadoId = null;

  public function __construct(
    private readonly Session $session,
    private readonly UsuarioRepository $usuarios,
  ) {
  }

  /**
   * @param array<string, mixed> $usuario
   * @param bool $mostrador entró con PIN en un mostrador: la sesión se cierra a los pocos minutos sin uso
   */
  public function login(array $usuario, bool $mostrador = false): void
  {
    // Nuevo id de sesión para evitar fijación de sesión.
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_regenerate_id(true);
    }

    $_SESSION[self::KEY] = [
      'id' => (int) $usuario['id'],
      'nombre' => (string) $usuario['nombre'],
      'usuario' => (string) $usuario['usuario'],
      'rol' => (string) $usuario['rol'],
      'actividad' => time(),
      'mostrador' => $mostrador,
    ];
  }

  /** ¿La sesión es de un mostrador (se entró con PIN)? */
  public function enMostrador(): bool
  {
    return (bool) ($this->user()['mostrador'] ?? false);
  }

  public function logout(): void
  {
    $this->verificadoId = null;
    unset($_SESSION[self::KEY]);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_regenerate_id(true);
    }
  }

  /** @return array{id: int, nombre: string, usuario: string, rol: string}|null */
  public function user(): ?array
  {
    $auth = $_SESSION[self::KEY] ?? null;
    if ($auth === null) {
      return null;
    }

    $maxima = !empty($auth['mostrador']) ? \App\Services\MostradorService::INACTIVIDAD : self::INACTIVIDAD_MAXIMA;
    if (time() - $auth['actividad'] > $maxima) {
      $this->logout();

      return null;
    }

    // Una vez por request se revalida contra la base: si el usuario fue desactivado o
    // eliminado se corta la sesión, y un cambio de rol o de nombre rige de inmediato.
    if ($this->verificadoId !== $auth['id']) {
      $usuario = $this->usuarios->find((int) $auth['id']);
      if ($usuario === null || !(int) $usuario['activo']) {
        $this->logout();

        return null;
      }
      $this->refrescar($usuario);
      $this->verificadoId = $auth['id'];
    }

    $_SESSION[self::KEY]['actividad'] = time();

    return $_SESSION[self::KEY];
  }

  public function id(): ?int
  {
    return $this->user()['id'] ?? null;
  }

  public function check(): bool
  {
    return $this->user() !== null;
  }

  /**
   * ¿Puede usar lo de administrador? Entrando con PIN en un mostrador, no: un PIN de 4 dígitos
   * en la PC compartida no alcanza para la configuración, los usuarios o los backups. Para eso,
   * se entra con usuario y contraseña.
   */
  public function esAdministrador(): bool
  {
    $usuario = $this->user();

    return ($usuario['rol'] ?? null) === Rol::Administrador->value && empty($usuario['mostrador']);
  }

  /** Actualiza los datos visibles (nombre/rol) si el usuario editó su propio registro. */
  public function refrescar(array $usuario): void
  {
    if (isset($_SESSION[self::KEY]) && $_SESSION[self::KEY]['id'] === (int) $usuario['id']) {
      $_SESSION[self::KEY]['nombre'] = (string) $usuario['nombre'];
      $_SESSION[self::KEY]['usuario'] = (string) $usuario['usuario'];
      $_SESSION[self::KEY]['rol'] = (string) $usuario['rol'];
    }
  }
}
