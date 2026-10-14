<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;

/**
 * Acumula errores de validación y los lanza juntos.
 */
final class Validator
{
  /** Máximo que admiten las columnas decimal(10,2) de importes y cantidades. */
  public const IMPORTE_MAXIMO = 99999999.99;

  /** @var list<string> */
  private array $errors = [];

  /** @var array<string, string> campo del formulario => su mensaje */
  private array $campos = [];

  /**
   * Registra $message si la condición NO se cumple. Con $campo (el name del formulario), al
   * volver al formulario el mensaje aparece debajo de ese campo, que queda marcado en rojo.
   */
  public function check(bool $condition, string $message, ?string $campo = null): self
  {
    if (!$condition) {
      $this->errors[] = $message;
      if ($campo !== null) {
        $this->campos[$campo] ??= $message;
      }
    }

    return $this;
  }

  public function fails(): bool
  {
    return $this->errors !== [];
  }

  /** @throws ValidationException */
  public function validate(): void
  {
    if ($this->fails()) {
      throw new ValidationException($this->errors, $this->campos);
    }
  }

  public static function fecha(string $value): bool
  {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
  }

  public static function hora(string $value): bool
  {
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value);
  }

  public static function soloLetras(string $value, int $min, int $max): bool
  {
    return (bool) preg_match('/^[\p{L}\s\'.-]{' . $min . ',' . $max . '}$/u', $value);
  }

  public static function largo(string $value, int $min, int $max): bool
  {
    $length = mb_strlen($value);

    return $length >= $min && $length <= $max;
  }

  /**
   * Decimal no negativo y dentro del rango de la base, escrito como se escribe en Argentina:
   * punto para los miles y coma para los decimales ("10.000" = diez mil, "1.234,50").
   * También acepta el punto decimal cuando no puede ser de miles ("46000.00", "12.5"), que
   * es como lo mandan los campos numéricos del navegador. Admite "$" y espacios alrededor;
   * no admite notación científica (1e9) ni signos.
   */
  public static function importe(string $value): ?float
  {
    $value = str_replace(['$', ' ', "\u{00A0}"], '', trim($value));
    if (str_contains($value, ',')) {
      // Con coma decimal, los puntos solo pueden ser de miles (y tienen que estar bien puestos).
      if (!preg_match('/^[1-9]\d{0,2}(\.\d{3})*,\d+$|^\d+,\d+$/', $value)) {
        return null;
      }
      $value = str_replace(['.', ','], ['', '.'], $value);
    } elseif (preg_match('/^[1-9]\d{0,2}(\.\d{3})+$/', $value)) {
      // "10.000", "1.250.000": puntos de miles. "0.500" no: un número no empieza con 0.
      $value = str_replace('.', '', $value);
    }
    if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
      return null;
    }
    $numero = round((float) $value, 2);

    return $numero <= self::IMPORTE_MAXIMO ? $numero : null;
  }

  /**
   * Cantidad (litros, unidades): como importe(), pero "1.250" se rechaza por ambiguo. En
   * plata siempre es mil doscientos cincuenta; en una cantidad puede ser "uno y cuarto" y
   * descontaría 1250 del stock. Se pide escribirlo sin punto (1250) o con coma (1,25).
   */
  public static function cantidad(string $value): ?float
  {
    return preg_match('/^\s*\d{1,3}\.\d{3}\s*$/', $value) ? null : self::importe($value);
  }

  /** Cadena vacía → null; si no, el texto recortado. */
  public static function nullable(string $value): ?string
  {
    $value = trim($value);

    return $value === '' ? null : $value;
  }
}
