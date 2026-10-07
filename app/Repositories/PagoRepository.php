<?php

declare(strict_types=1);

namespace App\Repositories;

final class PagoRepository extends Repository
{
  /** @return list<array<string, mixed>> */
  public function porOrden(int $ordenId): array
  {
    return $this->fetchAll(
      'SELECT p.id, p.fecha, p.monto, p.forma_pago, p.observacion, p.created_at, u.nombre AS usuario
         FROM pagos p LEFT JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.orden_id = ? ORDER BY p.fecha, p.id',
      [$ordenId]
    );
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne('SELECT id, orden_id, fecha, monto, forma_pago FROM pagos WHERE id = ?', [$id]);
  }

  public function totalPagado(int $ordenId): float
  {
    return (float) $this->fetchOne('SELECT COALESCE(SUM(monto), 0) AS total FROM pagos WHERE orden_id = ?', [$ordenId])['total'];
  }

  public function create(int $ordenId, string $fecha, float $monto, string $formaPago, ?string $observacion, ?int $usuarioId): int
  {
    return $this->insert(
      'INSERT INTO pagos (orden_id, fecha, monto, forma_pago, observacion, usuario_id) VALUES (?, ?, ?, ?, ?, ?)',
      [$ordenId, $fecha, $monto, $formaPago, $observacion, $usuarioId]
    );
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM pagos WHERE id = ?', [$id]);
  }

  /**
   * Clientes con saldo pendiente en órdenes finalizadas.
   *
   * @return list<array<string, mixed>>
   */
  public function deudores(): array
  {
    return $this->consultarDeudores('', []);
  }

  /** @return array<int, float> saldo adeudado (órdenes finalizadas) indexado por cliente */
  public function saldosPorCliente(): array
  {
    return array_map('floatval', array_column($this->deudores(), 'saldo', 'id'));
  }

  /**
   * Saldo adeudado solo de los clientes indicados (por ejemplo, los de una página del listado).
   *
   * @param list<int> $clienteIds
   * @return array<int, float>
   */
  public function saldosDe(array $clienteIds): array
  {
    if ($clienteIds === []) {
      return [];
    }
    $marcas = implode(',', array_fill(0, count($clienteIds), '?'));

    return array_map('floatval', array_column(
      $this->consultarDeudores("AND o.cliente_id IN ({$marcas})", array_map('intval', $clienteIds)),
      'saldo',
      'id'
    ));
  }

  /** @return list<array<string, mixed>> */
  private function consultarDeudores(string $filtro, array $params): array
  {
    return $this->fetchAll(
      "SELECT c.id, CONCAT(p.apellido, ', ', p.nombre) AS cliente, c.telefono,
              COUNT(*) AS ordenes, SUM(o.total - COALESCE(pg.pagado, 0)) AS saldo
         FROM ordenes o
         INNER JOIN clientes c ON c.id = o.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         LEFT JOIN (SELECT orden_id, SUM(monto) AS pagado FROM pagos GROUP BY orden_id) pg ON pg.orden_id = o.id
        WHERE o.estado = 'finalizado' AND o.total - COALESCE(pg.pagado, 0) > 0 {$filtro}
        GROUP BY c.id, p.apellido, p.nombre, c.telefono
        ORDER BY saldo DESC",
      $params
    );
  }
}
