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
  /** Aunque se use todo el tiempo, a las 12 h de entrar se vuelve a pedir la contraseña. */
  private const DURACION_MAXIMA = 12 * 3600;

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
      'inicio' => time(),
      'huella' => $this->usuarios->huella((int) $usuario['id']),
      'mostrador' => $mostrador,
    ];
  }

  /**
   * La sesión actual cambió su propia contraseña: sigue abierta (las demás de ese usuario se
   * cortan solas, porque su huella ya no coincide).
   */
  public function renovarHuella(): void
  {
    if (isset($_SESSION[self::KEY])) {
      $_SESSION[self::KEY]['huella'] = $this->usuarios->huella((int) $_SESSION[self::KEY]['id']);
      $this->verificadoId = null;
    }
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
    // Las sesiones de antes de la huella (sin "inicio") se cierran una vez: se vuelve a entrar.
    if (time() - $auth['actividad'] > $maxima || time() - ($auth['inicio'] ?? 0) > self::DURACION_MAXIMA) {
      $this->logout();

      return null;
    }

    // Una vez por request se revalida contra la base: si el usuario fue desactivado o
    // eliminado, o si cambió su contraseña desde otra sesión (o se la cambió un administrador),
    // se corta la sesión; un cambio de rol o de nombre rige de inmediato.
    if ($this->verificadoId !== $auth['id']) {
      $usuario = $this->usuarios->findParaSesion((int) $auth['id']);
      if ($usuario === null || !(int) $usuario['activo'] || !hash_equals((string) $usuario['huella'], (string) ($auth['huella'] ?? ''))) {
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
