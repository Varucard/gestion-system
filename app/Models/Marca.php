<?php

declare(strict_types=1);

namespace App\Models;

final class Marca
{
  public function __construct(
    public readonly string $nombre,
    public readonly ?int $id = null,
  ) {
  }
}
