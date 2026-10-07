<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoTurno: string
{
  case Pendiente = 'pendiente';
  case Confirmado = 'confirmado';
  case Realizado = 'realizado';
  case Cancelado = 'cancelado';
  case NoAsistio = 'no_asistio';

  public function label(): string
  {
    return match ($this) {
      self::Pendiente => 'Pendiente',
      self::Confirmado => 'Confirmado',
      self::Realizado => 'Realizado',
      self::Cancelado => 'Cancelado',
      self::NoAsistio => 'No asistió',
    };
  }

  /** Los turnos en estos estados no ocupan el horario. */
  public function liberaHorario(): bool
  {
    return $this === self::Cancelado || $this === self::NoAsistio;
  }
}
