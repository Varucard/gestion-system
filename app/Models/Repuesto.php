<?php

declare(strict_types=1);

namespace App\Models;

final class Repuesto
{
  public function __construct(
    public readonly string $nombre,
    public readonly float $precio,
    public readonly ?string $descripcion = null,
    public readonly ?string $codigo = null,
    public readonly float $stockMinimo = 0,
    public readonly ?int $proveedorId = null,
    public readonly ?int $id = null,
    public readonly ?float $precioCosto = null,
  ) {
  }
}
