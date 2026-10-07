<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Proveedor;

final class ProveedorRepository extends Repository
{
  private const COLUMNAS = 'id, nombre, cuit, contacto, telefono, email, direccion, observaciones, activo';

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(
      'SELECT p.id, p.nombre, p.cuit, p.contacto, p.telefono, p.email, p.direccion, p.observaciones, p.activo,
              (SELECT COUNT(*) FROM repuestos r WHERE r.proveedor_id = p.id) AS repuestos
         FROM proveedores p ORDER BY p.nombre'
    );
  }

  /** @return list<array<string, mixed>> */
  public function activos(): array
  {
    return $this->fetchAll('SELECT ' . self::COLUMNAS . ' FROM proveedores WHERE activo = 1 ORDER BY nombre');
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT ' . self::COLUMNAS . ' FROM proveedores WHERE id = ?', [$id]);
  }

  public function save(Proveedor $p): int
  {
    $params = [$p->nombre, $p->cuit, $p->contacto, $p->telefono, $p->email, $p->direccion, $p->observaciones, (int) $p->activo];

    if ($p->id === null) {
      return $this->insert(
        'INSERT INTO proveedores (nombre, cuit, contacto, telefono, email, direccion, observaciones, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        $params
      );
    }

    $this->execute(
      'UPDATE proveedores SET nombre = ?, cuit = ?, contacto = ?, telefono = ?, email = ?, direccion = ?,
              observaciones = ?, activo = ? WHERE id = ?',
      [...$params, $p->id]
    );

    return $p->id;
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM proveedores WHERE id = ?', [$id]);
  }
}
