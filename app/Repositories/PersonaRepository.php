<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Persona;

/**
 * Datos personales compartidos por clientes y empleados.
 */
final class PersonaRepository extends Repository
{
  /**
   * Devuelve el id de la persona con ese DNI, creándola si no existe.
   * Si ya existía (p. ej. un empleado que también es cliente) se actualizan sus datos.
   */
  public function obtenerOCrear(Persona $persona): int
  {
    $existente = $this->fetchOne('SELECT id FROM personas WHERE dni = ?', [$persona->dni]);

    if ($existente === null) {
      return $this->insert(
        'INSERT INTO personas (nombre, apellido, dni, email) VALUES (?, ?, ?, ?)',
        [$persona->nombre, $persona->apellido, $persona->dni, $persona->email]
      );
    }

    $this->actualizar((int) $existente['id'], $persona);

    return (int) $existente['id'];
  }

  public function actualizar(int $personaId, Persona $persona): void
  {
    $this->execute(
      'UPDATE personas SET nombre = ?, apellido = ?, email = ? WHERE id = ?',
      [$persona->nombre, $persona->apellido, $persona->email, $personaId]
    );
  }
}
