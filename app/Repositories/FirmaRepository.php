<?php

declare(strict_types=1);

namespace App\Repositories;

/** Firmas del cliente en la pantalla, por orden: al recibir el equipo y al entregarlo. */
final class FirmaRepository extends Repository
{
  /** @return array<string, array<string, mixed>> momento => firma */
  public function deOrden(int $ordenId): array
  {
    $firmas = [];
    foreach ($this->fetchAll('SELECT id, momento, archivo, aclaracion, created_at FROM orden_firmas WHERE orden_id = ?', [$ordenId]) as $f) {
      $firmas[$f['momento']] = $f;
    }

    return $firmas;
  }

  /**
   * ¿Ya hay firma de ese momento? Bloquea la orden hasta el fin de la transacción (la fila existe
   * siempre; un SELECT … FOR UPDATE sobre una firma que todavía no existe toma un candado de
   * rango y dos envíos a la vez se trababan): hay que llamarlo dentro de una.
   */
  public function existe(int $ordenId, string $momento): bool
  {
    $this->fetchOne('SELECT id FROM ordenes WHERE id = ? FOR UPDATE', [$ordenId]);

    return $this->fetchOne('SELECT 1 FROM orden_firmas WHERE orden_id = ? AND momento = ?', [$ordenId, $momento]) !== null;
  }

  public function guardar(int $ordenId, string $momento, string $archivo, ?string $aclaracion, ?int $usuarioId): void
  {
    $this->execute(
      'INSERT INTO orden_firmas (orden_id, momento, archivo, aclaracion, usuario_id) VALUES (?, ?, ?, ?, ?)',
      [$ordenId, $momento, $archivo, $aclaracion, $usuarioId]
    );
  }

  /** @return string|null el archivo de la firma borrada */
  public function eliminar(int $ordenId, string $momento): ?string
  {
    $firma = $this->fetchOne('SELECT archivo FROM orden_firmas WHERE orden_id = ? AND momento = ?', [$ordenId, $momento]);
    $this->execute('DELETE FROM orden_firmas WHERE orden_id = ? AND momento = ?', [$ordenId, $momento]);

    return $firma['archivo'] ?? null;
  }
}
