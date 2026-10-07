<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Indicadores para el panel de inicio.
 */
final class PanelRepository extends Repository
{
  /** @return array{pendiente: int, en_proceso: int} */
  public function ordenesAbiertasPorEstado(): array
  {
    $conteo = $this->fetchPairs(
      "SELECT estado, COUNT(*) FROM ordenes WHERE estado IN ('pendiente', 'en_proceso') GROUP BY estado"
    );

    return ['pendiente' => (int) ($conteo['pendiente'] ?? 0), 'en_proceso' => (int) ($conteo['en_proceso'] ?? 0)];
  }

  public function cobradoEntre(string $desde, string $hasta): float
  {
    return (float) $this->fetchOne(
      "SELECT COALESCE(SUM(pg.monto), 0) AS total FROM pagos pg
         INNER JOIN ordenes o ON o.id = pg.orden_id AND o.estado <> 'cancelado'
        WHERE pg.fecha BETWEEN ? AND ?",
      [$desde, $hasta]
    )['total'];
  }

  public function ordenesFinalizadasEntre(string $desde, string $hasta): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM ordenes WHERE estado = 'finalizado' AND fecha_realizado BETWEEN ? AND ?",
      [$desde, $hasta]
    )['total'];
  }
}
