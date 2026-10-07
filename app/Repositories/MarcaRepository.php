<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Marca;

final class MarcaRepository extends Repository
{
  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll('SELECT id, nombre FROM marcas ORDER BY nombre');
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT id, nombre FROM marcas WHERE id = ?', [$id]);
  }

  public function save(Marca $marca): int
  {
    if ($marca->id === null) {
      return $this->insert('INSERT INTO marcas (nombre) VALUES (?)', [$marca->nombre]);
    }

    $this->execute('UPDATE marcas SET nombre = ? WHERE id = ?', [$marca->nombre, $marca->id]);

    return $marca->id;
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM marcas WHERE id = ?', [$id]);
  }
}
