<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de validación o de regla de negocio, con mensajes aptos para el usuario.
 */
final class ValidationException extends RuntimeException
{
  /**
   * @param list<string> $errors
   * @param array<string, string> $campos  campo del formulario => mensaje (los que se pueden marcar en su campo)
   */
  public function __construct(private readonly array $errors, private readonly array $campos = [])
  {
    parent::__construct(implode(' ', $errors));
  }

  /** @return list<string> */
  public function errors(): array
  {
    return $this->errors;
  }

  /** @return array<string, string> */
  public function campos(): array
  {
    return $this->campos;
  }
}
