<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Rol;

final class UsuarioRepository extends Repository
{
  private const COLUMNAS = 'id, nombre, usuario, rol, activo, ultimo_acceso';

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

  public function registrarAcceso(int $id): void
  {
    $this->execute('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$id]);
  }
}
