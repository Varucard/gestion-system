<?php

declare(strict_types=1);

namespace App\Models;

final class Modelo
{
  public function __construct(
    public readonly int $marcaId,
    public readonly string $nombre,
    public readonly ?int $id = null,
  ) {
  }
}
