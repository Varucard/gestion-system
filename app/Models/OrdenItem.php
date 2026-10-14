<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ítem de una orden: un servicio o un repuesto, con cantidad y precio unitario
 * congelados al momento de cargarlo.
 *
 * Un repuesto puede ir "a costo" (sin margen; la marca es interna) o "provisto por el
 * cliente" (sin precio y sin mover stock). Este último puede ser del catálogo o una pieza
 * descrita a mano.
 */
final class OrdenItem
{
  private function __construct(
    public readonly ?int $servicioId,
    public readonly ?int $repuestoId,
    public readonly float $precioUnitario,
    public readonly float $cantidad,
    public readonly ?string $descripcion = null,
    public readonly bool $aCosto = false,
    public readonly bool $provistoCliente = false,
  ) {
  }

  public static function servicio(int $servicioId, float $precioUnitario, float $cantidad = 1): self
  {
    return new self($servicioId, null, $precioUnitario, $cantidad);
  }

  public static function repuesto(int $repuestoId, float $precioUnitario, float $cantidad = 1, bool $aCosto = false): self
  {
    return new self(null, $repuestoId, $precioUnitario, $cantidad, aCosto: $aCosto);
  }

  /** Repuesto que trae el cliente: del catálogo ($repuestoId) o descrito a mano ($descripcion). */
  public static function provistoPorCliente(?int $repuestoId, ?string $descripcion, float $cantidad = 1): self
  {
    return new self(null, $repuestoId, 0.0, $cantidad, $repuestoId === null ? $descripcion : null, provistoCliente: true);
  }

  public function esRepuesto(): bool
  {
    return $this->servicioId === null;
  }

  public function subtotal(): float
  {
    return round($this->precioUnitario * $this->cantidad, 2);
  }
}
