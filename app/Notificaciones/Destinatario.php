<?php

declare(strict_types=1);

namespace App\Notificaciones;

final class Destinatario
{
  public function __construct(
    public readonly string $nombre,
    public readonly ?string $email = null,
    public readonly ?string $telefono = null,
  ) {
  }
}
