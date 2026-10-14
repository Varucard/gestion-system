<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Demasiadas contraseñas equivocadas con la sesión abierta (cambiar la clave, definir el PIN):
 * quien la encontró abierta está probando. La sesión se cierra.
 */
final class CuentaBloqueadaException extends RuntimeException
{
}
