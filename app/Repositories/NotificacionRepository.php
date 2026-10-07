<?php

declare(strict_types=1);

namespace App\Repositories;

final class NotificacionRepository extends Repository
{
  public function registrar(?int $turnoId, string $tipo, string $canal, string $destino, string $estado, ?string $detalle = null, ?int $ordenId = null): void
  {
    $this->execute(
      'INSERT INTO notificaciones (turno_id, orden_id, tipo, canal, destino, estado, detalle) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [$turnoId, $ordenId, $tipo, $canal, mb_substr($destino, 0, 255), $estado, $detalle !== null ? mb_substr($detalle, 0, 500) : null]
    );
  }

  /** ¿Ya se le envió a la orden un aviso de este tipo? */
  public function enviadoDeOrden(int $ordenId, string $tipo): bool
  {
    return $this->fetchOne(
      "SELECT 1 FROM notificaciones WHERE orden_id = ? AND tipo = ? AND estado = 'enviado' LIMIT 1",
      [$ordenId, $tipo]
    ) !== null;
  }

  public function erroresRecientesDeOrden(int $ordenId, string $tipo, int $horas = 24): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM notificaciones
        WHERE orden_id = ? AND tipo = ? AND estado = 'error' AND created_at > NOW() - INTERVAL ? HOUR",
      [$ordenId, $tipo, $horas]
    )['total'];
  }

  public function erroresRecientes(int $turnoId, string $tipo, int $horas = 24): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM notificaciones
        WHERE turno_id = ? AND tipo = ? AND estado = 'error' AND created_at > NOW() - INTERVAL ? HOUR",
      [$turnoId, $tipo, $horas]
    )['total'];
  }

  /** @return list<array<string, mixed>> */
  public function recientes(int $limite = 100): array
  {
    return $this->fetchAll(
      "SELECT n.*, CONCAT(p.apellido, ', ', p.nombre) AS cliente, t.fecha AS turno_fecha, t.hora AS turno_hora
         FROM notificaciones n
         LEFT JOIN turnos t ON t.id = n.turno_id
         LEFT JOIN ordenes o ON o.id = n.orden_id
         LEFT JOIN equipos eq ON eq.id = o.equipo_id
         LEFT JOIN clientes c ON c.id = COALESCE(t.cliente_id, eq.cliente_id)
         LEFT JOIN personas p ON p.id = c.persona_id
        ORDER BY n.id DESC
        LIMIT " . max(1, $limite)
    );
  }
}
