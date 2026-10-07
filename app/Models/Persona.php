<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Datos comunes a cualquier persona del sistema (clientes y, a futuro, empleados).
 */
abstract class Persona
{
  public function __construct(
    public readonly string $nombre,
    public readonly string $apellido,
    public readonly string $dni,
    public readonly ?string $email = null,
    public readonly ?int $personaId = null,
  ) {
  }

  public function nombreCompleto(): string
  {
    return "{$this->apellido}, {$this->nombre}";
  }
}
