<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Estado;
use App\Models\Cliente;
use App\Support\ConsultaPaginada;

final class ClienteRepository extends Repository
{
  public function __construct(\PDO $db, private readonly PersonaRepository $personas)
  {
    parent::__construct($db);
  }

  private const SELECT = "
    SELECT c.id, c.persona_id, p.nombre, p.apellido, p.dni, p.email,
           c.telefono, c.direccion, c.estado, c.foto
      FROM clientes c
      INNER JOIN personas p ON p.id = c.persona_id";

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(self::SELECT . ' ORDER BY p.apellido, p.nombre');
  }

  /** @param array<string, mixed> $peticion parámetros de DataTables; filtro opcional: estado */
  public function paginar(array $peticion): array
  {
    $consulta = new ConsultaPaginada(self::SELECT, [
      ['sql' => 'p.nombre', 'buscar' => true],
      ['sql' => 'p.apellido', 'buscar' => true],
      ['sql' => 'p.dni', 'buscar' => true],
      ['sql' => 'c.telefono', 'buscar' => true],
      ['sql' => 'p.email', 'buscar' => true],
      ['sql' => 'c.direccion', 'buscar' => true],
      ['sql' => null],
      ['sql' => 'c.estado'],
      ['sql' => null],
    ], 'p.apellido, p.nombre');

    $estado = (string) ($peticion['estado'] ?? '');

    return $consulta->ejecutar($this->db, $peticion, in_array($estado, ['activo', 'inactivo'], true) ? [['c.estado = ?', [$estado]]] : []);
  }

  /** @return list<array<string, mixed>> */
  public function activos(): array
  {
    return $this->fetchAll(self::SELECT . " WHERE c.estado = 'activo' ORDER BY p.apellido, p.nombre");
  }

  /** @return array<string, mixed>|null */
  public function porDni(string $dni): ?array
  {
    return $this->fetchOne(self::SELECT . ' WHERE p.dni = ?', [$dni]);
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne(self::SELECT . ' WHERE c.id = ?', [$id]);
  }

  /** Crea la persona y el cliente en una única transacción. Devuelve el id del cliente. */
  public function create(Cliente $cliente): int
  {
    return $this->transaction(function () use ($cliente) {
      $personaId = $this->personas->obtenerOCrear($cliente);

      return $this->insert(
        'INSERT INTO clientes (persona_id, telefono, direccion, estado) VALUES (?, ?, ?, ?)',
        [$personaId, $cliente->telefono, $cliente->direccion, $cliente->estado->value]
      );
    });
  }

  /** Actualiza datos personales y de contacto. El DNI no se modifica. */
  public function update(Cliente $cliente): void
  {
    $this->transaction(function () use ($cliente) {
      $this->execute(
        'UPDATE personas p
           INNER JOIN clientes c ON c.persona_id = p.id
           SET p.nombre = ?, p.apellido = ?, p.email = ?
         WHERE c.id = ?',
        [$cliente->nombre, $cliente->apellido, $cliente->email, $cliente->id]
      );

      $this->execute(
        'UPDATE clientes SET telefono = ?, direccion = ? WHERE id = ?',
        [$cliente->telefono, $cliente->direccion, $cliente->id]
      );
    });
  }

  public function setFoto(int $id, ?string $archivo): void
  {
    $this->execute('UPDATE clientes SET foto = ? WHERE id = ?', [$archivo, $id]);
  }

  public function setEstado(int $id, Estado $estado): void
  {
    $this->execute('UPDATE clientes SET estado = ? WHERE id = ?', [$estado->value, $id]);
  }

  /** Borra el cliente y también la persona si no es empleado. */
  public function delete(int $id): void
  {
    $this->transaction(function () use ($id) {
      $personaId = (int) $this->fetchOne('SELECT persona_id FROM clientes WHERE id = ?', [$id])['persona_id'];
      $this->execute('DELETE FROM clientes WHERE id = ?', [$id]);
      $this->execute(
        'DELETE FROM personas WHERE id = ? AND NOT EXISTS (SELECT 1 FROM empleados WHERE persona_id = ?)',
        [$personaId, $personaId]
      );
    });
  }

  public function tieneHistorial(int $id): bool
  {
    $row = $this->fetchOne(
      'SELECT EXISTS(SELECT 1 FROM equipos WHERE cliente_id = ?)
           OR EXISTS(SELECT 1 FROM turnos WHERE cliente_id = ?) AS tiene',
      [$id, $id]
    );

    return (bool) $row['tiene'];
  }
}
