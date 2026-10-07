<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TurnoRepository;
use App\Support\HorarioAtencion;
use App\Support\Validator;
use DateTimeImmutable;

/** Grilla semanal de turnos con los cupos libres de cada franja. */
final class AgendaService
{
  public function __construct(
    private readonly TurnoRepository $turnos,
    private readonly ConfiguracionService $configuracion,
  ) {
  }

  /**
   * @return array{
   *   desde: string, hasta: string, anterior: string, siguiente: string,
   *   dias: list<array{fecha: string, nombre: string, abierto: bool, feriado: bool}>,
   *   franjas: list<string>,
   *   celdas: array<string, array<string, array{abierta: bool, pasada: bool, turnos: list<array<string, mixed>>, libres: int}>>
   * }
   */
  public function semana(?string $fecha, DateTimeImmutable $ahora): array
  {
    $base = $fecha && Validator::fecha($fecha) ? new DateTimeImmutable($fecha) : $ahora;
    $lunes = $base->modify('monday this week')->setTime(0, 0);
    $domingo = $lunes->modify('+6 days');

    $config = $this->configuracion->seccion('turnos');
    $horario = $this->configuracion->horario();
    $intervalo = (int) $config['intervalo_minutos'];
    $cupos = (int) $config['cupos_por_horario'];

    $dias = [];
    $apertura = '23:59';
    $cierre = '00:00';
    for ($i = 0; $i < 7; $i++) {
      $dia = $lunes->modify("+{$i} days")->format('Y-m-d');
      $franja = $horario->franja($dia);
      $dias[] = ['fecha' => $dia, 'nombre' => HorarioAtencion::DIAS[$i + 1], 'abierto' => $franja !== null, 'feriado' => $horario->esFeriado($dia)];
      if ($franja) {
        $apertura = min($apertura, $franja['desde']);
        $cierre = max($cierre, $franja['hasta']);
      }
    }

    $franjas = [];
    for ($t = strtotime("today {$apertura}"); $apertura < $cierre && date('H:i', $t) < $cierre; $t += $intervalo * 60) {
      $franjas[] = date('H:i', $t);
    }

    // Turnos agrupados por día y franja (un turno cae en la franja que contiene su hora).
    $porFranja = [];
    foreach ($this->turnos->activosEntre($lunes->format('Y-m-d'), $domingo->format('Y-m-d')) as $turno) {
      $minutos = (int) substr($turno['hora'], 0, 2) * 60 + (int) substr($turno['hora'], 3, 2);
      $inicio = (int) substr($apertura, 0, 2) * 60 + (int) substr($apertura, 3, 2);
      $indice = max(0, intdiv($minutos - $inicio, $intervalo));
      $clave = $franjas[$indice] ?? end($franjas);
      $porFranja[$turno['fecha']][$clave][] = $turno;
    }

    $celdas = [];
    foreach ($dias as $dia) {
      foreach ($franjas as $hora) {
        $turnos = $porFranja[$dia['fecha']][$hora] ?? [];
        $ocupados = count(array_filter($turnos, fn($t) => substr($t['hora'], 0, 5) === $hora));
        $celdas[$dia['fecha']][$hora] = [
          'abierta' => $horario->dentroDeHorario($dia['fecha'], $hora),
          'pasada' => "{$dia['fecha']} {$hora}" < $ahora->format('Y-m-d H:i'),
          'turnos' => $turnos,
          'libres' => max(0, $cupos - $ocupados),
        ];
      }
    }

    return [
      'desde' => $lunes->format('Y-m-d'),
      'hasta' => $domingo->format('Y-m-d'),
      'anterior' => $lunes->modify('-7 days')->format('Y-m-d'),
      'siguiente' => $lunes->modify('+7 days')->format('Y-m-d'),
      'dias' => $dias,
      'franjas' => $franjas,
      'celdas' => $celdas,
    ];
  }
}
