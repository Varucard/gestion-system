<?php

declare(strict_types=1);

namespace App\Repositories;

final class StockRepository extends Repository
{
  /**
   * Suma (o resta, si es negativa) $cantidad al stock del repuesto y registra el
   * movimiento con el stock resultante. Devuelve el stock final.
   */
  public function registrar(
    int $repuestoId,
    string $tipo,
    float $cantidad,
    ?int $ordenId = null,
    ?int $proveedorId = null,
    ?int $usuarioId = null,
    ?string $motivo = null,
    ?float $costoUnitario = null,
  ): float {
    return $this->transaction(function () use ($repuestoId, $tipo, $cantidad, $ordenId, $proveedorId, $usuarioId, $motivo, $costoUnitario) {
      // Bloquea la fila para que dos movimientos simultáneos no pisen el saldo.
      $actual = $this->fetchOne('SELECT stock_actual FROM repuestos WHERE id = ? FOR UPDATE', [$repuestoId]);
      $resultante = round((float) $actual['stock_actual'] + $cantidad, 2);

      $this->execute('UPDATE repuestos SET stock_actual = ? WHERE id = ?', [$resultante, $repuestoId]);
      $this->insert(
        'INSERT INTO movimientos_stock (repuesto_id, tipo, cantidad, costo_unitario, stock_resultante, orden_id, proveedor_id, usuario_id, motivo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$repuestoId, $tipo, $cantidad, $costoUnitario, $resultante, $ordenId, $proveedorId, $usuarioId, $motivo]
      );

      return $resultante;
    });
  }

  /** @return list<array<string, mixed>> */
  public function historial(int $repuestoId): array
  {
    return $this->fetchAll(
      'SELECT m.id, m.tipo, m.cantidad, m.costo_unitario, m.stock_resultante, m.orden_id, m.motivo, m.created_at,
              p.nombre AS proveedor, u.nombre AS usuario
         FROM movimientos_stock m
         LEFT JOIN proveedores p ON p.id = m.proveedor_id
         LEFT JOIN usuarios u ON u.id = m.usuario_id
        WHERE m.repuesto_id = ?
        ORDER BY m.id DESC',
      [$repuestoId]
    );
  }
}
