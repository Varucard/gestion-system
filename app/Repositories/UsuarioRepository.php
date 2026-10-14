<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Rol;

final class UsuarioRepository extends Repository
{
  private const COLUMNAS = 'id, nombre, usuario, rol, activo, ultimo_acceso, pin_hash IS NOT NULL AS tiene_pin';

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll('SELECT ' . self::COLUMNAS . ' FROM usuarios ORDER BY nombre');
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT ' . self::COLUMNAS . ' FROM usuarios WHERE id = ?', [$id]);
  }

  /** @return array<string, mixed>|null incluye password_hash */
  public function findParaLogin(string $usuario): ?array
  {
    return $this->fetchOne('SELECT ' . self::COLUMNAS . ', password_hash FROM usuarios WHERE usuario = ?', [$usuario]);
  }

  /**
   * Huella de la contraseña actual (un hash del hash): la sesión la guarda al entrar y, si la
   * contraseña cambia, deja de coincidir y la sesión se corta (ver Auth).
   */
  public function huella(int $id): ?string
  {
    return $this->fetchOne('SELECT LEFT(SHA2(password_hash, 256), 32) AS huella FROM usuarios WHERE id = ?', [$id])['huella'] ?? null;
  }

  /** @return array<string, mixed>|null lo de find() más la huella de la contraseña */
  public function findParaSesion(int $id): ?array
  {
    return $this->fetchOne('SELECT ' . self::COLUMNAS . ', LEFT(SHA2(password_hash, 256), 32) AS huella FROM usuarios WHERE id = ?', [$id]);
  }

  public function contar(): int
  {
    return (int) $this->fetchOne('SELECT COUNT(*) AS total FROM usuarios')['total'];
  }

  public function contarAdministradoresActivos(): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'administrador' AND activo = 1"
    )['total'];
  }

  public function create(string $nombre, string $usuario, string $hash, Rol $rol): int
  {
    return $this->insert(
      'INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)',
      [$nombre, $usuario, $hash, $rol->value]
    );
  }

  public function update(int $id, string $nombre, string $usuario, Rol $rol, bool $activo): void
  {
    $this->execute(
      'UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ? WHERE id = ?',
      [$nombre, $usuario, $rol->value, (int) $activo, $id]
    );
  }

  public function setPassword(int $id, string $hash): void
  {
    $this->execute('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$hash, $id]);
  }

  /** PIN del modo mostrador (null: se le quita). */
  public function setPin(int $id, ?string $hash): void
  {
    $this->execute('UPDATE usuarios SET pin_hash = ? WHERE id = ?', [$hash, $id]);
  }

  /** @return string|null el hash del PIN, si el usuario está activo y tiene uno */
  public function pinHash(int $id): ?string
  {
    return $this->fetchOne('SELECT pin_hash FROM usuarios WHERE id = ? AND activo = 1', [$id])['pin_hash'] ?? null;
  }

  /** @return list<array{id: int|string, nombre: string}> los que pueden entrar con PIN en el mostrador */
  public function conPin(): array
  {
    return $this->fetchAll('SELECT id, nombre FROM usuarios WHERE activo = 1 AND pin_hash IS NOT NULL ORDER BY nombre');
  }

  public function registrarAcceso(int $id): void
  {
    $this->execute('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$id]);
  }
}
