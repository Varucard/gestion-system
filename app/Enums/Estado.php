<?php

declare(strict_types=1);

namespace App\Enums;

/** Estado de alta lógica para clientes y equipos. */
enum Estado: string
{
  case Activo = 'activo';
  case Inactivo = 'inactivo';

  public function alternar(): self
  {
    return $this === self::Activo ? self::Inactivo : self::Activo;
  }
}
