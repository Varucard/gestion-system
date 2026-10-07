<?php

declare(strict_types=1);

namespace App\Enums;

enum Rol: string
{
  case Administrador = 'administrador';
  case Empleado = 'empleado';

  public function label(): string
  {
    return match ($this) {
      self::Administrador => 'Administrador',
      self::Empleado => 'Empleado',
    };
  }
}
