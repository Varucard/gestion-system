<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\ConsultaPaginada;
use App\Support\Validator;

final class AuditoriaRepository extends Repository
{
  public function registrar(
    ?int $usuarioId,
    ?string $usuarioNombre,
    string $accion,
    string $entidad,
    ?int $entidadId,
    string $descripcion,
    array $datos,
    ?string $ip,
  ): void {
    $this->execute(
      'INSERT INTO auditoria (usuario_id, usuario_nombre, accion, entidad, entidad_id, descripcion, datos, ip)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $usuarioId, $usuarioNombre, $accion, $entidad, $entidadId, mb_substr($descripcion, 0, 255),
        $datos === [] ? null : json_encode($datos, JSON_UNESCAPED_UNICODE), $ip,
      ]
    );
  }

  /** @return list<array<string, mixed>> historial de una entidad (p. ej. una orden) */
  public function deEntidad(string $entidad, int $id): array
  {
    return $this->fetchAll(
      'SELECT id, usuario_nombre, accion, descripcion, datos, created_at FROM auditoria
        WHERE entidad = ? AND entidad_id = ? ORDER BY id DESC',
      [$entidad, $id]
    );
  }

  /**
   * Página de la auditoría con filtros opcionales: entidad, usuario_id, desde, hasta.
   *
   * @param array<string, mixed> $peticion
   */
  public function paginar(array $peticion): array
  {
    $consulta = new ConsultaPaginada(
      'SELECT a.id, a.usuario_nombre, a.accion, a.entidad, a.entidad_id, a.descripcion, a.datos, a.ip, a.created_at FROM auditoria a',
      [
        ['sql' => 'a.created_at'],
        ['sql' => 'a.usuario_nombre', 'buscar' => true],
        ['sql' => 'a.accion', 'buscar' => true],
        ['sql' => 'a.entidad', 'buscar' => true],
        ['sql' => 'a.descripcion', 'buscar' => true],
        ['sql' => null],
      ],
      'a.id DESC',
    );

    $filtros = [];
    if (($peticion['entidad'] ?? '') !== '') {
      $filtros[] = ['a.entidad = ?', [(string) $peticion['entidad']]];
    }
    if ((int) ($peticion['usuario_id'] ?? 0) > 0) {
      $filtros[] = ['a.usuario_id = ?', [(int) $peticion['usuario_id']]];
    }
    if (Validator::fecha((string) ($peticion['desde'] ?? ''))) {
      $filtros[] = ['a.created_at >= ?', [$peticion['desde'] . ' 00:00:00']];
    }
    if (Validator::fecha((string) ($peticion['hasta'] ?? ''))) {
      $filtros[] = ['a.created_at <= ?', [$peticion['hasta'] . ' 23:59:59']];
    }

    return $consulta->ejecutar($this->db, $peticion, $filtros);
  }

  /** @return list<string> */
  public function entidades(): array
  {
    return array_column($this->fetchAll('SELECT DISTINCT entidad FROM auditoria ORDER BY entidad'), 'entidad');
  }
}
