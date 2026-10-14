<?php

declare(strict_types=1);

namespace App\Repositories;

/** Respuestas de la encuesta de usabilidad (SUS). */
final class EncuestaRepository extends Repository
{
  public function guardar(int $usuarioId, string $ronda, string $respuestas, float $puntaje, ?string $comentario): void
  {
    $this->execute(
      'INSERT INTO encuestas_sus (usuario_id, ronda, respuestas, puntaje, comentario) VALUES (?, ?, ?, ?, ?)',
      [$usuarioId, $ronda, $respuestas, $puntaje, $comentario]
    );
  }

  public function respondio(int $usuarioId, string $ronda): bool
  {
    return $this->fetchOne('SELECT 1 FROM encuestas_sus WHERE usuario_id = ? AND ronda = ?', [$usuarioId, $ronda]) !== null;
  }

  /** @return list<array{ronda: string, respuestas: int|string, promedio: float|string, minimo: float|string, maximo: float|string, desde: string, hasta: string}> */
  public function resumenPorRonda(): array
  {
    return $this->fetchAll(
      'SELECT ronda, COUNT(*) AS respuestas, AVG(puntaje) AS promedio, MIN(puntaje) AS minimo, MAX(puntaje) AS maximo,
              MIN(created_at) AS desde, MAX(created_at) AS hasta
         FROM encuestas_sus GROUP BY ronda ORDER BY MIN(created_at)'
    );
  }

  /** @return list<array<string, mixed>> respuestas por pregunta y comentarios de una ronda (sin decir quién) */
  public function deRonda(string $ronda): array
  {
    return $this->fetchAll('SELECT respuestas, puntaje, comentario, created_at FROM encuestas_sus WHERE ronda = ? ORDER BY created_at', [$ronda]);
  }
}
