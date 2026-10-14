<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\IntentoRepository;
use App\Repositories\MostradorRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Validator;

/**
 * Modo mostrador: en la PC o la tablet que usan varios, cada uno entra con su PIN de 4
 * dígitos tocando su nombre, y la sesión se cierra sola a los pocos minutos sin uso.
 *
 * Un PIN es mucho más fácil de adivinar que una contraseña, así que:
 *   - solo sirve desde un navegador habilitado como mostrador por un administrador (cookie
 *     con un token al azar; acá se guarda su hash),
 *   - después de 5 intentos fallidos el usuario queda bloqueado 15 minutos en ese equipo,
 *   - no se aceptan PIN obvios (1234, 0000, 1212…).
 */
final class MostradorService
{
  public const COOKIE = 'mostrador';
  public const LARGO_PIN = 4;
  public const MAX_INTENTOS = 5;
  public const MAX_INTENTOS_IP = 20;
  public const MAX_INTENTOS_USUARIO = 10;
  public const MINUTOS_BLOQUEO = 15;
  /** Sin uso, la sesión de mostrador se cierra a los 10 minutos (no a las 8 horas). */
  public const INACTIVIDAD = 10 * 60;

  private const AMBITO = 'pin';

  public function __construct(
    private readonly MostradorRepository $mostradores,
    private readonly UsuarioRepository $usuarios,
    private readonly IntentoRepository $intentos,
    private readonly Auditor $auditor,
    private readonly \App\Core\Logger $logger,
  ) {
  }

  /**
   * Habilita este navegador como mostrador.
   *
   * @return string el token para guardar en la cookie (no se puede recuperar después)
   */
  public function habilitar(string $nombre, ?int $usuarioId): string
  {
    $nombre = trim($nombre);
    (new Validator())->check(Validator::largo($nombre, 2, 60), 'Poné un nombre para reconocer este equipo (por ejemplo, "PC del mostrador").')->validate();

    $token = bin2hex(random_bytes(32));
    $id = $this->mostradores->crear($nombre, hash('sha256', $token), $usuarioId);
    $this->auditor->registrar('habilitar_mostrador', 'sistema', $id, "Se habilitó el modo mostrador en \"{$nombre}\"");

    return $token;
  }

  /** @return array<string, mixed>|null el mostrador de esta cookie, si está habilitado */
  public function deCookie(?string $token): ?array
  {
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $this->mostradores->porToken(hash('sha256', $token)) : null;
  }

  /** @return list<array<string, mixed>> */
  public function listar(): array
  {
    return $this->mostradores->all();
  }

  public function quitar(int $id): void
  {
    $this->mostradores->eliminar($id);
    $this->auditor->registrar('quitar_mostrador', 'sistema', $id, 'Se quitó un equipo del modo mostrador');
  }

  /** @return list<array<string, mixed>> */
  public function usuariosConPin(): array
  {
    return $this->usuarios->conPin();
  }

  /**
   * Verifica el PIN desde un mostrador habilitado. Devuelve el usuario o lanza ValidationException.
   *
   * @param array<string, mixed> $mostrador
   * @return array<string, mixed>
   */
  public function entrar(array $mostrador, int $usuarioId, string $pin, string $ip): array
  {
    $clave = "{$mostrador['id']}:{$usuarioId}";
    if ($this->intentos->bloqueado(self::AMBITO, $clave, $ip, self::MINUTOS_BLOQUEO, self::MAX_INTENTOS, self::MAX_INTENTOS_IP, self::MAX_INTENTOS_USUARIO)) {
      throw new ValidationException([sprintf('Demasiados PIN equivocados. Esperá %d minutos o entrá con tu usuario y contraseña.', self::MINUTOS_BLOQUEO)]);
    }

    // El intento se anota antes de verificar (y se borra si acierta): varios pedidos a la vez no
    // pasan todos el control de arriba antes de que se registre el primer error.
    $this->intentos->registrar(self::AMBITO, $clave, $ip);
    $hash = $this->usuarios->pinHash($usuarioId);
    if ($hash === null || !password_verify($pin, $hash)) {
      $this->logger->warning('PIN equivocado en el mostrador "{mostrador}"', ['mostrador' => $mostrador['nombre'], 'usuario_id' => $usuarioId, 'ip' => $ip]);
      $quedan = self::MAX_INTENTOS - $this->intentos->contar(self::AMBITO, $clave, $ip, self::MINUTOS_BLOQUEO);
      throw new ValidationException([match (true) {
        $quedan <= 0 => sprintf('El PIN no es correcto. Por seguridad, tu PIN queda bloqueado %d minutos en este equipo: mientras tanto, entrá con usuario y contraseña.', self::MINUTOS_BLOQUEO),
        $quedan === 1 => 'El PIN no es correcto. Te queda 1 intento antes de que se bloquee por unos minutos.',
        default => "El PIN no es correcto. Te quedan {$quedan} intentos.",
      }]);
    }

    $this->intentos->limpiar(self::AMBITO, $clave);
    $this->usuarios->registrarAcceso($usuarioId);
    $this->mostradores->usado((int) $mostrador['id']);

    return $this->usuarios->find($usuarioId);
  }

  /** ¿Es un PIN aceptable? 4 dígitos, sin repetir el mismo ni seguir una escalera. */
  public static function pinValido(string $pin): bool
  {
    if (!preg_match('/^\d{' . self::LARGO_PIN . '}$/', $pin)) {
      return false;
    }
    $obvios = ['1234', '2345', '3456', '4567', '5678', '6789', '0123', '4321', '9876', '8765', '7654', '6543', '5432', '3210', '1212', '2580', '1122', '1004'];

    return count(array_unique(str_split($pin))) > 1 && !in_array($pin, $obvios, true);
  }
}
