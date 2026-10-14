<?php

declare(strict_types=1);

namespace App\Repositories;

/** Navegadores habilitados como mostrador (se entra con PIN). */
final class MostradorRepository extends Repository
{
  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(
      'SELECT m.id, m.nombre, m.created_at, m.ultimo_uso, u.nombre AS activado_por
         FROM mostradores m LEFT JOIN usuarios u ON u.id = m.usuario_id ORDER BY m.nombre'
    );
  }

  public function crear(string $nombre, string $tokenHash, ?int $usuarioId): int
  {
    return $this->insert('INSERT INTO mostradores (nombre, token_hash, usuario_id) VALUES (?, ?, ?)', [$nombre, $tokenHash, $usuarioId]);
  }

  /** @return array<string, mixed>|null */
  public function porToken(string $tokenHash): ?array
  {
    return $this->fetchOne('SELECT id, nombre FROM mostradores WHERE token_hash = ?', [$tokenHash]);
  }

  public function usado(int $id): void
  {
    $this->execute('UPDATE mostradores SET ultimo_uso = NOW() WHERE id = ?', [$id]);
  }

  public function eliminar(int $id): void
  {
    $this->execute('DELETE FROM mostradores WHERE id = ?', [$id]);
  }
}
