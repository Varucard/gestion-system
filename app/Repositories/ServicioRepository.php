<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Servicio;

final class ServicioRepository extends Repository
{
  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll('SELECT id, nombre, descripcion, precio_base FROM servicios ORDER BY nombre');
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT id, nombre, descripcion, precio_base FROM servicios WHERE id = ?', [$id]);
  }

  /**
   * @param list<int> $ids
   * @return array<int, float> precio_base indexado por id
   */
  public function precios(array $ids): array
  {
    if ($ids === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    return array_map('floatval', $this->fetchPairs("SELECT id, precio_base FROM servicios WHERE id IN ({$placeholders})", $ids));
  }

  public function save(Servicio $servicio): int
  {
    $params = [$servicio->nombre, $servicio->descripcion, $servicio->precioBase];

    if ($servicio->id === null) {
      return $this->insert('INSERT INTO servicios (nombre, descripcion, precio_base) VALUES (?, ?, ?)', $params);
    }

    $this->execute('UPDATE servicios SET nombre = ?, descripcion = ?, precio_base = ? WHERE id = ?', [...$params, $servicio->id]);

    return $servicio->id;
  }

  /** Con $esperado, solo actualiza si el precio sigue siendo ese; devuelve si lo actualizó. */
  public function setPrecio(int $id, float $precio, ?float $esperado = null): bool
  {
    if ($esperado === null) {
      $this->execute('UPDATE servicios SET precio_base = ? WHERE id = ?', [$precio, $id]);

      return true;
    }

    return $this->execute('UPDATE servicios SET precio_base = ? WHERE id = ? AND precio_base = ?', [$precio, $id, $esperado]) > 0;
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM servicios WHERE id = ?', [$id]);
  }
}
