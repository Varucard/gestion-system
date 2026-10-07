<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoEquipo: string
{
  case Notebook = 'notebook';
  case Pc = 'pc';
  case AllInOne = 'all_in_one';
  case Monitor = 'monitor';
  case Impresora = 'impresora';
  case Celular = 'celular';
  case Tablet = 'tablet';
  case Consola = 'consola';
  case Otro = 'otro';

  public function label(): string
  {
    return match ($this) {
      self::Notebook => 'Notebook',
      self::Pc => 'PC de escritorio',
      self::AllInOne => 'All-in-one',
      self::Monitor => 'Monitor',
      self::Impresora => 'Impresora',
      self::Celular => 'Celular',
      self::Tablet => 'Tablet',
      self::Consola => 'Consola',
      self::Otro => 'Otro',
    };
  }

  /** Nombre del tipo a partir del valor guardado ('' si no hay o no es válido). */
  public static function etiqueta(?string $valor): string
  {
    return self::tryFrom((string) $valor)?->label() ?? '';
  }
}
