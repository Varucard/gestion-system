<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoTurno;

final class Turno
{
  public function __construct(
    public readonly int $clienteId,
    public readonly int $equipoId,
    public readonly string $fecha,
    public readonly string $hora,
    public readonly ?string $descripcion = null,
    public readonly EstadoTurno $estado = EstadoTurno::Pendiente,
    public readonly ?int $id = null,
  ) {
  }
}
