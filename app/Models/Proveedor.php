<?php

declare(strict_types=1);

namespace App\Models;

final class Proveedor
{
  public function __construct(
    public readonly string $nombre,
    public readonly ?string $cuit = null,
    public readonly ?string $contacto = null,
    public readonly ?string $telefono = null,
    public readonly ?string $email = null,
    public readonly ?string $direccion = null,
    public readonly ?string $observaciones = null,
    public readonly bool $activo = true,
    public readonly ?int $id = null,
  ) {
  }
}
