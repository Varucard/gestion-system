<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoOrden;

/**
 * Orden de reparación sobre un equipo, compuesta por ítems (servicios y repuestos).
 */
final class Orden
{
  /** @param list<OrdenItem> $items */
  public function __construct(
    public readonly int $equipoId,
    public readonly array $items,
    public readonly EstadoOrden $estado = EstadoOrden::Pendiente,
    public readonly ?int $id = null,
    public readonly ?int $tecnicoId = null,
    public readonly ?string $fallaReportada = null,
    public readonly ?string $accesorios = null,
    public readonly ?string $estadoIngreso = null,
    public readonly ?string $diagnostico = null,
    public readonly ?string $trabajoRealizado = null,
    public readonly ?string $notasInternas = null,
    public readonly ?string $proximoMantenimientoFecha = null,
    public readonly ?int $turnoId = null,
  ) {
  }

  public function total(): float
  {
    return round(array_sum(array_map(fn(OrdenItem $item) => $item->subtotal(), $this->items)), 2);
  }
}
