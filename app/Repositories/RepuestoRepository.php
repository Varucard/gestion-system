<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Repuesto;

final class RepuestoRepository extends Repository
{
  private const SELECT = '
    SELECT r.id, r.codigo, r.nombre, r.descripcion, r.precio, r.precio_costo, r.stock_actual, r.stock_minimo,
           r.proveedor_id, p.nombre AS proveedor
      FROM repuestos r
      LEFT JOIN proveedores p ON p.id = r.proveedor_id';

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(self::SELECT . ' ORDER BY r.nombre');
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne(self::SELECT . ' WHERE r.id = ?', [$id]);
  }

  /** Repuestos con stock en o por debajo del mínimo (solo los que tienen mínimo definido). */
  public function bajoMinimo(): array
  {
    return $this->fetchAll(self::SELECT . ' WHERE r.stock_minimo > 0 AND r.stock_actual <= r.stock_minimo ORDER BY r.nombre');
  }

  /**
   * @param list<int> $ids
   * @return array<int, float> precio indexado por id
   */
  public function precios(array $ids): array
  {
    if ($ids === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    return array_map('floatval', $this->fetchPairs("SELECT id, precio FROM repuestos WHERE id IN ({$placeholders})", $ids));
  }

  /**
   * @param list<int> $ids
   * @return array<int, ?float> precio de costo indexado por id (null si no tiene costo cargado)
   */
  public function costos(array $ids): array
  {
    if ($ids === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    return array_map(
      fn($costo) => $costo === null ? null : (float) $costo,
      $this->fetchPairs("SELECT id, precio_costo FROM repuestos WHERE id IN ({$placeholders})", $ids),
    );
  }

  public function save(Repuesto $r): int
  {
    $params = [$r->codigo, $r->nombre, $r->descripcion, $r->precio, $r->precioCosto, $r->stockMinimo, $r->proveedorId];

    if ($r->id === null) {
      return $this->insert(
        'INSERT INTO repuestos (codigo, nombre, descripcion, precio, precio_costo, stock_minimo, proveedor_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
        $params
      );
    }

    $this->execute(
      'UPDATE repuestos SET codigo = ?, nombre = ?, descripcion = ?, precio = ?, precio_costo = ?, stock_minimo = ?, proveedor_id = ? WHERE id = ?',
      [...$params, $r->id]
    );

    return $r->id;
  }

  /** Con $esperado, solo actualiza si el precio sigue siendo ese; devuelve si lo actualizó. */
  public function setPrecio(int $id, float $precio, ?float $esperado = null): bool
  {
    if ($esperado === null) {
      $this->execute('UPDATE repuestos SET precio = ? WHERE id = ?', [$precio, $id]);

      return true;
    }

    return $this->execute('UPDATE repuestos SET precio = ? WHERE id = ? AND precio = ?', [$precio, $id, $esperado]) > 0;
  }

  public function setCosto(int $id, float $costo): void
  {
    $this->execute('UPDATE repuestos SET precio_costo = ? WHERE id = ?', [$costo, $id]);
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM repuestos WHERE id = ?', [$id]);
  }

  /** ¿Hay alguno cargado? (Primeros pasos: sin traer la lista entera.) */
  public function hayAlguno(): bool
  {
    return $this->fetchOne('SELECT 1 FROM repuestos LIMIT 1') !== null;
  }
}
