<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoOrden: string
{
  case Pendiente = 'pendiente';
  case EnProceso = 'en_proceso';
  case Finalizado = 'finalizado';
  case Cancelado = 'cancelado';

  public function label(): string
  {
    return match ($this) {
      self::Pendiente => 'Pendiente',
      self::EnProceso => 'En proceso',
      self::Finalizado => 'Finalizado',
      self::Cancelado => 'Cancelado',
    };
  }
}
