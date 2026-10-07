<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Repositories\AuditoriaRepository;
use Throwable;

/**
 * Registra en la auditoría las acciones relevantes del negocio, con el usuario
 * y la IP de la petición, y deja además una línea en el log.
 *
 *   $auditor->registrar('cambiar_estado', 'orden', 12, 'Orden #12: pendiente → finalizado', ['antes' => …]);
 */
final class Auditor
{
  public function __construct(
    private readonly AuditoriaRepository $auditoria,
    private readonly Auth $auth,
    private readonly Logger $logger,
  ) {
  }

  /**
   * @param string|null $actor nombre a registrar cuando la acción no la hace un usuario
   *                           logueado (p. ej. "Cliente (link)", "Sistema")
   */
  public function registrar(string $accion, string $entidad, ?int $entidadId, string $descripcion, array $datos = [], ?string $actor = null): void
  {
    $usuario = $actor === null ? $this->auth->user() : null;

    try {
      $this->auditoria->registrar(
        $usuario['id'] ?? null,
        $usuario['nombre'] ?? $actor ?? (PHP_SAPI === 'cli' ? 'Sistema' : null),
        $accion,
        $entidad,
        $entidadId,
        $descripcion,
        $datos,
        PHP_SAPI === 'cli' ? null : \App\Core\Request::ip(),
      );
    } catch (Throwable $e) {
      // La auditoría nunca debe impedir la operación principal.
      $this->logger->error('No se pudo registrar la auditoría', ['exception' => $e, 'accion' => $accion]);
    }

    $this->logger->info($descripcion, ['accion' => $accion, 'entidad' => $entidad, 'id' => $entidadId] + ($datos ? ['datos' => $datos] : []));
  }
}
