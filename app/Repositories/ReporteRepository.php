<?php

declare(strict_types=1);

namespace App\Repositories;

/** Consultas de los reportes de gestión. Fechas inclusive (AAAA-MM-DD). */
final class ReporteRepository extends Repository
{
  /** @return list<array<string, mixed>> cobrado por mes y forma de pago (sin órdenes canceladas) */
  public function cobranzas(string $desde, string $hasta): array
  {
    return $this->fetchAll(
      "SELECT DATE_FORMAT(pg.fecha, '%Y-%m') AS mes, pg.forma_pago, COUNT(*) AS pagos, SUM(pg.monto) AS total
         FROM pagos pg
         INNER JOIN ordenes o ON o.id = pg.orden_id AND o.estado <> 'cancelado'
        WHERE pg.fecha BETWEEN ? AND ?
        GROUP BY mes, pg.forma_pago ORDER BY mes, total DESC",
      [$desde, $hasta]
    );
  }

  /** @return list<array<string, mixed>> servicios o repuestos más vendidos (órdenes no canceladas, por fecha de la orden) */
  public function masVendidos(string $tipo, string $desde, string $hasta): array
  {
    [$tabla, $columna] = $tipo === 'repuesto' ? ['repuestos', 'repuesto_id'] : ['servicios', 'servicio_id'];

    return $this->fetchAll(
      "SELECT x.nombre, COUNT(DISTINCT os.orden_id) AS ordenes, SUM(os.cantidad) AS cantidad, SUM(os.costo) AS total
         FROM ordenes_servicios os
         INNER JOIN ordenes o ON o.id = os.orden_id AND o.estado <> 'cancelado'
         INNER JOIN {$tabla} x ON x.id = os.{$columna}
        WHERE o.created_at >= ? AND o.created_at < ? + INTERVAL 1 DAY
        GROUP BY x.id, x.nombre ORDER BY total DESC",
      [$desde, $hasta]
    );
  }

  /** @return list<array<string, mixed>> órdenes por técnico */
  public function porTecnico(string $desde, string $hasta): array
  {
    return $this->fetchAll(
      "SELECT COALESCE(CONCAT(p.apellido, ', ', p.nombre), 'Sin asignar') AS tecnico,
              COUNT(*) AS ordenes,
              SUM(o.estado = 'finalizado') AS finalizadas,
              SUM(o.estado IN ('pendiente', 'en_proceso')) AS abiertas,
              SUM(CASE WHEN o.estado <> 'cancelado' THEN o.total ELSE 0 END) AS total
         FROM ordenes o
         LEFT JOIN empleados e ON e.id = o.tecnico_id
         LEFT JOIN personas p ON p.id = e.persona_id
        WHERE o.created_at >= ? AND o.created_at < ? + INTERVAL 1 DAY
        GROUP BY o.tecnico_id, p.apellido, p.nombre ORDER BY total DESC",
      [$desde, $hasta]
    );
  }

  /** @return list<array<string, mixed>> stock valorizado a costo y a precio de venta (al día de hoy) */
  public function stockValorizado(): array
  {
    return $this->fetchAll(
      'SELECT r.codigo, r.nombre, r.stock_actual, r.precio_costo, r.precio,
              r.stock_actual * COALESCE(r.precio_costo, 0) AS valor_costo,
              r.stock_actual * r.precio AS valor_venta
         FROM repuestos r WHERE r.stock_actual <> 0 ORDER BY valor_venta DESC'
    );
  }
}
