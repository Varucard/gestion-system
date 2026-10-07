<?php

declare(strict_types=1);

namespace App\Notificaciones;

final class Mensaje
{
  /** @param list<array{nombre: string, contenido: string, tipo: string}> $adjuntos */
  public function __construct(
    public readonly string $asunto,
    public readonly string $texto,
    public readonly array $adjuntos = [],
  ) {
  }
}
