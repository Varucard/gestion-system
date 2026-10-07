<?php

declare(strict_types=1);

namespace App\Models;

final class Servicio
{
  public function __construct(
    public readonly string $nombre,
    public readonly float $precioBase,
    public readonly ?string $descripcion = null,
    public readonly ?int $id = null,
  ) {
  }
}
