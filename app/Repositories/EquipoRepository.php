<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Estado;
use App\Models\Equipo;
use App\Support\ConsultaPaginada;

final class EquipoRepository extends Repository
{
  private const SELECT = "
    SELECT eq.id, eq.cliente_id, eq.tipo, eq.marca_id, eq.modelo_id, eq.numero_serie, eq.estado,
           eq.procesador, eq.memoria, eq.almacenamiento, eq.color, eq.detalle,
           CONCAT(p.apellido, ', ', p.nombre) AS cliente,
           ma.nombre AS marca, mo.nombre AS modelo
      FROM equipos eq
      INNER JOIN clientes c ON c.id = eq.cliente_id
      INNER JOIN personas p ON p.id = c.persona_id
      INNER JOIN marcas ma ON ma.id = eq.marca_id
      INNER JOIN modelos mo ON mo.id = eq.modelo_id";

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->fetchAll(self::SELECT . ' ORDER BY eq.id DESC');
  }

  /** @param array<string, mixed> $peticion parámetros de DataTables; filtro opcional: estado */
  public function paginar(array $peticion): array
  {
    $consulta = new ConsultaPaginada(self::SELECT, [
      ['sql' => "CONCAT(p.apellido, ', ', p.nombre)", 'buscar' => true],
      ['sql' => 'eq.tipo'],
      ['sql' => 'ma.nombre', 'buscar' => true],
      ['sql' => 'mo.nombre', 'buscar' => true],
      ['sql' => 'eq.numero_serie', 'buscar' => true],
      ['sql' => 'eq.estado'],
      ['sql' => null],
    ], 'eq.id DESC');

    $estado = (string) ($peticion['estado'] ?? '');

    return $consulta->ejecutar($this->db, $peticion, in_array($estado, ['activo', 'inactivo'], true) ? [['eq.estado = ?', [$estado]]] : []);
  }

  /** @return list<array<string, mixed>> */
  public function activos(): array
  {
    return $this->fetchAll(self::SELECT . " WHERE eq.estado = 'activo' ORDER BY ma.nombre, mo.nombre");
  }

  /** @return list<array<string, mixed>> */
  public function activosPorCliente(int $clienteId): array
  {
    return $this->fetchAll(
      self::SELECT . " WHERE eq.cliente_id = ? AND eq.estado = 'activo' ORDER BY ma.nombre, mo.nombre",
      [$clienteId]
    );
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne(self::SELECT . ' WHERE eq.id = ?', [$id]);
  }

  public function contarActivosPorCliente(int $clienteId): int
  {
    $row = $this->fetchOne(
      "SELECT COUNT(*) AS total FROM equipos WHERE cliente_id = ? AND estado = 'activo'",
      [$clienteId]
    );

    return (int) $row['total'];
  }

  public function create(Equipo $equipo): int
  {
    return $this->insert(
      'INSERT INTO equipos (cliente_id, tipo, marca_id, modelo_id, numero_serie, estado,
                            procesador, memoria, almacenamiento, color, detalle)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $equipo->clienteId, $equipo->tipo->value, $equipo->marcaId, $equipo->modeloId,
        $equipo->numeroSerie, $equipo->estado->value, ...$this->extras($equipo),
      ]
    );
  }

  public function update(Equipo $equipo): void
  {
    $this->execute(
      'UPDATE equipos
          SET cliente_id = ?, tipo = ?, marca_id = ?, modelo_id = ?, numero_serie = ?,
              procesador = ?, memoria = ?, almacenamiento = ?, color = ?, detalle = ?
        WHERE id = ?',
      [
        $equipo->clienteId, $equipo->tipo->value, $equipo->marcaId, $equipo->modeloId,
        $equipo->numeroSerie, ...$this->extras($equipo), $equipo->id,
      ]
    );
  }

  /** @return list<mixed> */
  private function extras(Equipo $e): array
  {
    return [$e->procesador, $e->memoria, $e->almacenamiento, $e->color, $e->detalle];
  }

  /** @return list<array<string, mixed>> */
  public function porCliente(int $clienteId): array
  {
    return $this->fetchAll(self::SELECT . ' WHERE eq.cliente_id = ? ORDER BY eq.estado, ma.nombre, mo.nombre', [$clienteId]);
  }

  /** @return list<array<string, mixed>> */
  public function imagenes(int $equipoId): array
  {
    return $this->fetchAll(
      'SELECT id, archivo, descripcion, created_at FROM equipo_imagenes WHERE equipo_id = ? ORDER BY id DESC',
      [$equipoId]
    );
  }

  /** @return array<string, mixed>|null */
  public function imagen(int $imagenId): ?array
  {
    return $this->fetchOne('SELECT id, equipo_id, archivo FROM equipo_imagenes WHERE id = ?', [$imagenId]);
  }

  public function agregarImagen(int $equipoId, string $archivo, ?string $descripcion, ?int $usuarioId): int
  {
    return $this->insert(
      'INSERT INTO equipo_imagenes (equipo_id, archivo, descripcion, usuario_id) VALUES (?, ?, ?, ?)',
      [$equipoId, $archivo, $descripcion, $usuarioId]
    );
  }

  public function eliminarImagen(int $imagenId): void
  {
    $this->execute('DELETE FROM equipo_imagenes WHERE id = ?', [$imagenId]);
  }

  /** Último próximo mantenimiento cargado para el equipo (de su orden más reciente con ese dato). */
  public function proximoMantenimiento(int $id): ?array
  {
    return $this->fetchOne(
      'SELECT id AS orden_id, proximo_mantenimiento_fecha, proximo_mantenimiento_avisado
         FROM ordenes
        WHERE equipo_id = ? AND proximo_mantenimiento_fecha IS NOT NULL AND estado <> ?
        ORDER BY id DESC LIMIT 1',
      [$id, 'cancelado']
    );
  }

  public function setEstado(int $id, Estado $estado): void
  {
    $this->execute('UPDATE equipos SET estado = ? WHERE id = ?', [$estado->value, $id]);
  }

  public function perteneceACliente(int $equipoId, int $clienteId): bool
  {
    return $this->fetchOne(
      'SELECT 1 FROM equipos WHERE id = ? AND cliente_id = ?',
      [$equipoId, $clienteId]
    ) !== null;
  }
}
