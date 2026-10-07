<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\IntentoRepository;
use App\Repositories\Repository;
use App\Repositories\UsuarioRepository;
use App\Support\Validator;
use PDOException;

final class UsuarioService
{
  /** Intentos fallidos para el mismo usuario desde la misma IP. */
  public const MAX_INTENTOS = 5;
  /** Desde una IP con cualquier usuario, y para un usuario desde cualquier IP. */
  public const MAX_INTENTOS_IP = 20;
  public const MAX_INTENTOS_USUARIO = 50;
  public const MINUTOS_BLOQUEO = 15;
  public const LARGO_MINIMO_CLAVE = 8;

  private const AMBITO = 'login';

  public function __construct(
    private readonly UsuarioRepository $usuarios,
    private readonly IntentoRepository $intentos,
    private readonly Auditor $auditor,
    private readonly \App\Core\Logger $logger,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->usuarios->find($id) ?? throw new NotFoundException('Usuario no encontrado.');
  }

  public function hayUsuarios(): bool
  {
    return $this->usuarios->contar() > 0;
  }

  /**
   * Verifica las credenciales. Devuelve el usuario (sin hash) o lanza ValidationException.
   *
   * @return array<string, mixed>
   */
  public function autenticar(string $usuario, string $clave, string $ip): array
  {
    $usuario = mb_strtolower(trim($usuario));

    if ($this->intentos->bloqueado(self::AMBITO, $usuario, $ip, self::MINUTOS_BLOQUEO, self::MAX_INTENTOS, self::MAX_INTENTOS_IP, self::MAX_INTENTOS_USUARIO)) {
      throw new ValidationException([
        sprintf('Demasiados intentos fallidos. Esperá %d minutos e intentá de nuevo.', self::MINUTOS_BLOQUEO),
      ]);
    }

    $fila = $usuario !== '' ? $this->usuarios->findParaLogin($usuario) : null;

    // Si el usuario no existe se verifica igual contra un hash ficticio, para no
    // revelar qué usuarios existen por la diferencia en el tiempo de respuesta.
    static $hashFicticio = null;
    $hash = $fila['password_hash'] ?? ($hashFicticio ??= password_hash(random_bytes(16), PASSWORD_DEFAULT));
    $valida = password_verify($clave, $hash);

    if ($fila === null || !$valida || !$fila['activo']) {
      $this->intentos->registrar(self::AMBITO, $usuario, $ip);
      $this->logger->warning('Intento de ingreso fallido para "{usuario}"', ['usuario' => $usuario, 'ip' => $ip]);
      throw new ValidationException(['Usuario o contraseña incorrectos.']);
    }

    if (password_needs_rehash($fila['password_hash'], PASSWORD_DEFAULT)) {
      $this->usuarios->setPassword((int) $fila['id'], password_hash($clave, PASSWORD_DEFAULT));
    }

    $this->intentos->limpiar(self::AMBITO, $usuario);
    $this->usuarios->registrarAcceso((int) $fila['id']);
    unset($fila['password_hash']);

    return $fila;
  }

  /** @param array<string, mixed> $input */
  public function crear(array $input, ?Rol $forzarRol = null): int
  {
    [$nombre, $usuario, $rol] = $this->datos($input, $forzarRol);
    $clave = (string) ($input['clave'] ?? '');
    $this->validarClave($clave, (string) ($input['clave_confirmacion'] ?? ''));

    try {
      $id = $this->usuarios->create($nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $rol);
      $this->auditor->registrar('crear', 'usuario', $id, "Usuario creado: {$usuario} ({$rol->label()})");

      return $id;
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ese nombre de usuario ya existe.']) : $e;
    }
  }

  /** @param array<string, mixed> $input */
  public function actualizar(int $id, array $input, int $usuarioActualId): void
  {
    $actual = $this->obtener($id);
    [$nombre, $usuario, $rol] = $this->datos($input);
    $activo = !empty($input['activo']);

    $dejaDeSerAdmin = $actual['rol'] === Rol::Administrador->value && $actual['activo']
      && ($rol !== Rol::Administrador || !$activo);

    (new Validator())
      ->check($id !== $usuarioActualId || ($activo && $rol === Rol::Administrador), 'No podés quitarte el rol de administrador ni desactivar tu propio usuario.')
      ->check(!$dejaDeSerAdmin || $this->usuarios->contarAdministradoresActivos() > 1, 'Tiene que quedar al menos un administrador activo.')
      ->validate();

    // La clave nueva se valida antes de guardar nada, para no dejar cambios a medias.
    $clave = (string) ($input['clave'] ?? '');
    if ($clave !== '') {
      $this->validarClave($clave, (string) ($input['clave_confirmacion'] ?? ''));
    }

    try {
      $this->usuarios->transaction(function () use ($id, $nombre, $usuario, $rol, $activo, $clave) {
        $this->usuarios->update($id, $nombre, $usuario, $rol, $activo);
        if ($clave !== '') {
          $this->usuarios->setPassword($id, password_hash($clave, PASSWORD_DEFAULT));
        }
      });
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ese nombre de usuario ya existe.']) : $e;
    }

    $this->auditor->registrar('editar', 'usuario', $id, "Usuario editado: {$usuario}", array_filter([
      'rol' => $actual['rol'] !== $rol->value ? "{$actual['rol']} → {$rol->value}" : null,
      'activo' => (bool) $actual['activo'] !== $activo ? ($activo ? 'reactivado' : 'desactivado') : null,
    ]));
    if ($clave !== '') {
      $this->auditor->registrar('cambiar_clave', 'usuario', $id, "Se cambió la contraseña de {$usuario}");
    }
  }

  public function cambiarClave(int $id, string $actual, string $nueva, string $confirmacion): void
  {
    $usuario = $this->obtener($id);
    $fila = $this->usuarios->findParaLogin($usuario['usuario']);

    (new Validator())->check(password_verify($actual, $fila['password_hash']), 'La contraseña actual no es correcta.')->validate();
    $this->validarClave($nueva, $confirmacion);

    $this->usuarios->setPassword($id, password_hash($nueva, PASSWORD_DEFAULT));
    $this->auditor->registrar('cambiar_clave', 'usuario', $id, "{$usuario['usuario']} cambió su contraseña");
  }

  /** Desbloquea el usuario (lo usa bin/usuario.php al reiniciar la clave). */
  public function desbloquear(string $usuario): void
  {
    $this->intentos->limpiar(self::AMBITO, mb_strtolower($usuario));
  }

  /** @return array{0: string, 1: string, 2: Rol} */
  private function datos(array $input, ?Rol $forzarRol = null): array
  {
    $nombre = trim((string) ($input['nombre'] ?? ''));
    $usuario = mb_strtolower(trim((string) ($input['usuario'] ?? '')));
    $rol = $forzarRol ?? Rol::tryFrom((string) ($input['rol'] ?? ''));

    (new Validator())
      ->check(Validator::largo($nombre, 2, 100), 'El nombre debe tener entre 2 y 100 caracteres.')
      ->check((bool) preg_match('/^[a-z0-9._-]{3,50}$/', $usuario), 'El usuario debe tener entre 3 y 50 caracteres (letras, números, punto, guion).')
      ->check($rol !== null, 'Seleccioná un rol válido.')
      ->validate();

    return [$nombre, $usuario, $rol];
  }

  private function validarClave(string $clave, string $confirmacion): void
  {
    (new Validator())
      ->check(mb_strlen($clave) >= self::LARGO_MINIMO_CLAVE, sprintf('La contraseña debe tener al menos %d caracteres.', self::LARGO_MINIMO_CLAVE))
      ->check($clave === $confirmacion, 'Las contraseñas no coinciden.')
      ->validate();
  }
}
