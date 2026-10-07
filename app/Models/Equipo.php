<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Estado;
use App\Enums\TipoEquipo;

final class Equipo
{
  public function __construct(
    public readonly int $clienteId,
    public readonly TipoEquipo $tipo,
    public readonly int $marcaId,
    public readonly int $modeloId,
    public readonly ?string $numeroSerie = null,
    public readonly Estado $estado = Estado::Activo,
    public readonly ?int $id = null,
    public readonly ?string $procesador = null,
    public readonly ?string $memoria = null,
    public readonly ?string $almacenamiento = null,
    public readonly ?string $color = null,
    public readonly ?string $detalle = null,
  ) {
  }
}
