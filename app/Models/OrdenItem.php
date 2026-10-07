<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ítem de una orden: un servicio o un repuesto, con cantidad y precio unitario
 * congelados al momento de cargarlo.
 */
final class OrdenItem
{
  private function __construct(
    public readonly ?int $servicioId,
    public readonly ?int $repuestoId,
    public readonly float $precioUnitario,
    public readonly float $cantidad,
  ) {
  }

  public static function servicio(int $servicioId, float $precioUnitario, float $cantidad = 1): self
  {
    return new self($servicioId, null, $precioUnitario, $cantidad);
  }

  public static function repuesto(int $repuestoId, float $precioUnitario, float $cantidad = 1): self
  {
    return new self(null, $repuestoId, $precioUnitario, $cantidad);
  }

  public function esRepuesto(): bool
  {
    return $this->repuestoId !== null;
  }

  public function subtotal(): float
  {
    return round($this->precioUnitario * $this->cantidad, 2);
  }
}
