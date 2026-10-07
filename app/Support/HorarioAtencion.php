<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Horario de atención: días hábiles, franja horaria por día y feriados.
 */
final class HorarioAtencion
{
  public const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

  /**
   * @param array<string|int, array{desde: string, hasta: string}|null> $horario por día ISO (1 = lunes)
   * @param list<string> $feriados fechas AAAA-MM-DD
   */
  public function __construct(
    private readonly array $horario,
    private readonly array $feriados = [],
  ) {
  }

  /** @param array<string, mixed> $turnos sección "turnos" de la configuración */
  public static function desdeConfig(array $turnos): self
  {
    return new self($turnos['horario'] ?? [], $turnos['feriados'] ?? []);
  }

  public function esFeriado(string $fecha): bool
  {
    return in_array($fecha, $this->feriados, true);
  }

  /** @return array{desde: string, hasta: string}|null franja del día o null si cierra */
  public function franja(string $fecha): ?array
  {
    if ($this->esFeriado($fecha)) {
      return null;
    }

    return $this->horario[(string) (new DateTimeImmutable($fecha))->format('N')] ?? null;
  }

  public function esDiaHabil(string $fecha): bool
  {
    return $this->franja($fecha) !== null;
  }

  /** ¿La hora (HH:MM) está dentro de la franja de ese día? El horario de cierre no admite turnos. */
  public function dentroDeHorario(string $fecha, string $hora): bool
  {
    $franja = $this->franja($fecha);
    $hora = substr($hora, 0, 5);

    return $franja !== null && $hora >= $franja['desde'] && $hora < $franja['hasta'];
  }

  public function abiertoAhora(DateTimeImmutable $ahora): bool
  {
    $franja = $this->franja($ahora->format('Y-m-d'));
    $hora = $ahora->format('H:i');

    return $franja !== null && $hora >= $franja['desde'] && $hora <= $franja['hasta'];
  }

  /** Próximo día hábil posterior a $fecha (busca hasta 60 días). */
  public function siguienteDiaHabil(string $fecha): ?string
  {
    $dia = new DateTimeImmutable($fecha);
    for ($i = 1; $i <= 60; $i++) {
      $candidato = $dia->modify("+{$i} day")->format('Y-m-d');
      if ($this->esDiaHabil($candidato)) {
        return $candidato;
      }
    }

    return null;
  }

  /** Texto legible, p. ej. "Lunes a Viernes 08:00 a 18:00 · Sábado 08:00 a 13:00". */
  public function resumen(): string
  {
    $grupos = [];
    foreach (self::DIAS as $n => $nombre) {
      $f = $this->horario[(string) $n] ?? null;
      $clave = $f ? "{$f['desde']} a {$f['hasta']}" : null;
      $ultimo = array_key_last($grupos);

      if ($ultimo !== null && $grupos[$ultimo]['clave'] === $clave && $grupos[$ultimo]['hasta'] === $n - 1) {
        $grupos[$ultimo]['hasta'] = $n;
      } else {
        $grupos[] = ['clave' => $clave, 'desde' => $n, 'hasta' => $n];
      }
    }

    $partes = [];
    foreach ($grupos as $g) {
      if ($g['clave'] === null) {
        continue;
      }
      $dias = $g['desde'] === $g['hasta'] ? self::DIAS[$g['desde']] : self::DIAS[$g['desde']] . ' a ' . self::DIAS[$g['hasta']];
      $partes[] = "{$dias} {$g['clave']}";
    }

    return implode(' · ', $partes);
  }
}
