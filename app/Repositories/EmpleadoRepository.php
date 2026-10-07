<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Estado;
use App\Models\Empleado;
use PDO;

final class EmpleadoRepository extends Repository
{
  private const SELECT = "
    SELECT e.id, e.persona_id, p.nombre, p.apellido, p.dni, p.email,
           e.telefono, e.puesto, e.fecha_ingreso, e.estado,
           (SELECT COUNT(*) FROM ordenes o WHERE o.tecnico_id = e.id) AS ordenes
      FROM empleados e
      INNER JOIN personas p ON p.id = e.persona_id";

  public function __construct(PDO $db, private readonly PersonaRepository $personas)
  {
    parent::__construct($db);
  }

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(self::SELECT . ' ORDER BY p.apellido, p.nombre');
  }

  /** @return list<array<string, mixed>> */
  public function activos(): array
  {
    return $this->fetchAll(self::SELECT . " WHERE e.estado = 'activo' ORDER BY p.apellido, p.nombre");
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne(self::SELECT . ' WHERE e.id = ?', [$id]);
  }

  public function create(Empleado $empleado): int
  {
    return $this->transaction(function () use ($empleado) {
      $personaId = $this->personas->obtenerOCrear($empleado);

      return $this->insert(
        'INSERT INTO empleados (persona_id, telefono, puesto, fecha_ingreso, estado) VALUES (?, ?, ?, ?, ?)',
        [$personaId, $empleado->telefono, $empleado->puesto, $empleado->fechaIngreso, $empleado->estado->value]
      );
    });
  }

  public function update(Empleado $empleado): void
  {
    $this->transaction(function () use ($empleado) {
      $actual = $this->find((int) $empleado->id);
      $this->personas->actualizar((int) $actual['persona_id'], $empleado);
      $this->execute(
        'UPDATE empleados SET telefono = ?, puesto = ?, fecha_ingreso = ? WHERE id = ?',
        [$empleado->telefono, $empleado->puesto, $empleado->fechaIngreso, $empleado->id]
      );
    });
  }

  public function setEstado(int $id, Estado $estado): void
  {
    $this->execute('UPDATE empleados SET estado = ? WHERE id = ?', [$estado->value, $id]);
  }
}
