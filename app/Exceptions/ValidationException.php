<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de validación o de regla de negocio, con mensajes aptos para el usuario.
 */
final class ValidationException extends RuntimeException
{
  /** @param list<string> $errors */
  public function __construct(private readonly array $errors)
  {
    parent::__construct(implode(' ', $errors));
  }

  /** @return list<string> */
  public function errors(): array
  {
    return $this->errors;
  }
}
