<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoOrden: string
{
  case Pendiente = 'pendiente';
  case EnProceso = 'en_proceso';
  case Finalizado = 'finalizado';
  case Cancelado = 'cancelado';

  /**
   * Nombre que ve la gente: el mismo en el sistema y en el portal del cliente
   * ("Recibido → En reparación → Listo"), en vez de la jerga de la base de datos.
   */
  public function label(): string
  {
    return match ($this) {
      self::Pendiente => 'Recibido',
      self::EnProceso => 'En reparación',
      self::Finalizado => 'Listo',
      self::Cancelado => 'Cancelado',
    };
  }
}
