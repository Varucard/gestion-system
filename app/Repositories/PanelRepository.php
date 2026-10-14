<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Indicadores para el panel de inicio.
 */
final class PanelRepository extends Repository
{
  /**
   * Equipos que están en el local, por etapa: recibidos, en reparación y listos sin retirar.
   *
   * @return array{pendiente: int, en_proceso: int, listo: int}
   */
  public function ordenesAbiertasPorEstado(): array
  {
    $conteo = $this->fetchPairs(
      "SELECT estado, COUNT(*) FROM ordenes
        WHERE estado IN ('pendiente', 'en_proceso') OR (estado = 'finalizado' AND entregado_en IS NULL)
        GROUP BY estado"
    );

    return [
      'pendiente' => (int) ($conteo['pendiente'] ?? 0),
      'en_proceso' => (int) ($conteo['en_proceso'] ?? 0),
      'listo' => (int) ($conteo['finalizado'] ?? 0),
    ];
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

  /** Cuántos pagos se cobraron entre esas fechas (de órdenes no canceladas). */
  public function pagosEntre(string $desde, string $hasta): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM pagos pg
         INNER JOIN ordenes o ON o.id = pg.orden_id AND o.estado <> 'cancelado'
        WHERE pg.fecha BETWEEN ? AND ?",
      [$desde, $hasta]
    )['total'];
  }

  /** Turnos de ese día que siguen en pie (sin cancelados ni ausentes), como TurnoRepository::delDia(). */
  public function turnosDelDia(string $fecha): int
  {
    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM turnos WHERE fecha = ? AND estado NOT IN ('cancelado', 'no_asistio')",
      [$fecha]
    )['total'];
  }

  /** Repuestos con stock en o por debajo del mínimo, como RepuestoRepository::bajoMinimo(). */
  public function repuestosBajoMinimo(): int
  {
    return (int) $this->fetchOne(
      'SELECT COUNT(*) AS total FROM repuestos WHERE stock_minimo > 0 AND stock_actual <= stock_minimo'
    )['total'];
  }

  /**
   * Clientes que deben y total adeudado: órdenes terminadas con saldo, como PagoRepository::deudores().
   *
   * @return array{clientes: int, total: float}
   */
  public function deuda(): array
  {
    $fila = $this->fetchOne(
      "SELECT COUNT(DISTINCT o.cliente_id) AS clientes, COALESCE(SUM(o.total - COALESCE(pg.pagado, 0)), 0) AS total
         FROM ordenes o
         LEFT JOIN (SELECT orden_id, SUM(monto) AS pagado FROM pagos GROUP BY orden_id) pg ON pg.orden_id = o.id
        WHERE o.estado = 'finalizado' AND o.total - COALESCE(pg.pagado, 0) > 0"
    );

    return ['clientes' => (int) $fila['clientes'], 'total' => (float) $fila['total']];
  }

  /** Columnas comunes de las alertas: la orden, el equipo y el cliente. */
  private const ALERTA = "SELECT o.id, o.total, o.fecha_realizado, o.presupuesto_enviado, eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente, c.telefono,
              o.total - COALESCE((SELECT SUM(pg.monto) FROM pagos pg WHERE pg.orden_id = o.id), 0) AS saldo,
              (SELECT MAX(n.created_at) FROM notificaciones n WHERE n.orden_id = o.id AND n.tipo = 'listo' AND n.estado = 'enviado') AS avisado_en
         FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id
         INNER JOIN clientes c ON c.id = o.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id";

  /** @return list<array<string, mixed>> equipos listos que no se retiraron hace $dias días o más (avisado_en: el último aviso de "listo") */
  public function listosSinRetirar(int $dias): array
  {
    return $this->fetchAll(
      self::ALERTA . " WHERE o.estado = 'finalizado' AND o.entregado_en IS NULL AND o.fecha_realizado <= CURDATE() - INTERVAL ? DAY
        ORDER BY o.fecha_realizado",
      [$dias]
    );
  }

  /** @return list<array<string, mixed>> presupuestos enviados hace $dias días o más que el cliente no respondió */
  public function presupuestosSinRespuesta(int $dias): array
  {
    return $this->fetchAll(
      self::ALERTA . " WHERE o.estado IN ('pendiente', 'en_proceso') AND o.presupuesto_respuesta IS NULL
          AND o.presupuesto_enviado <= NOW() - INTERVAL ? DAY
        ORDER BY o.presupuesto_enviado",
      [$dias]
    );
  }
}
