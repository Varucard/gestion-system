<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Estado;

final class Empleado extends Persona
{
  public function __construct(
    string $nombre,
    string $apellido,
    string $dni,
    public readonly string $puesto,
    public readonly ?string $telefono = null,
    public readonly ?string $fechaIngreso = null,
    ?string $email = null,
    public readonly Estado $estado = Estado::Activo,
    public readonly ?int $id = null,
    ?int $personaId = null,
  ) {
    parent::__construct($nombre, $apellido, $dni, $email, $personaId);
  }
}
