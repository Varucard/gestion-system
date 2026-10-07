<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Modelo;

final class ModeloRepository extends Repository
{
  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(
      'SELECT m.id, m.nombre, m.marca_id, ma.nombre AS marca
         FROM modelos m
         INNER JOIN marcas ma ON ma.id = m.marca_id
        ORDER BY ma.nombre, m.nombre'
    );
  }

  /** @return list<array<string, mixed>> */
  public function porMarca(int $marcaId): array
  {
    return $this->fetchAll(
      'SELECT id, nombre FROM modelos WHERE marca_id = ? ORDER BY nombre',
      [$marcaId]
    );
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT id, marca_id, nombre FROM modelos WHERE id = ?', [$id]);
  }

  public function perteneceAMarca(int $modeloId, int $marcaId): bool
  {
    return $this->fetchOne('SELECT 1 FROM modelos WHERE id = ? AND marca_id = ?', [$modeloId, $marcaId]) !== null;
  }

  public function save(Modelo $modelo): int
  {
    if ($modelo->id === null) {
      return $this->insert(
        'INSERT INTO modelos (marca_id, nombre) VALUES (?, ?)',
        [$modelo->marcaId, $modelo->nombre]
      );
    }

    $this->execute(
      'UPDATE modelos SET marca_id = ?, nombre = ? WHERE id = ?',
      [$modelo->marcaId, $modelo->nombre, $modelo->id]
    );

    return $modelo->id;
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM modelos WHERE id = ?', [$id]);
  }
}
