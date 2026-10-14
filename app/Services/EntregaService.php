<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Repositories\OrdenRepository;
use App\Support\Validator;

/**
 * Entrega del equipo: el cliente lo retira (con la orden ya lista) y, si quiere, firma la
 * conformidad en la pantalla. Una orden lista sin entregar es un equipo que sigue en el local.
 */
final class EntregaService
{
  public function __construct(
    private readonly OrdenRepository $ordenes,
    private readonly OrdenService $ordenService,
    private readonly FirmaService $firmas,
    private readonly Auditor $auditor,
  ) {
  }

  /**
   * @param string|null $firma  dibujo de la firma (data URL PNG); vacío = sin firma
   */
  public function entregar(int $ordenId, ?string $firma, ?string $aclaracion, ?int $usuarioId): void
  {
    $this->ordenService->obtener($ordenId);

    // Con la orden bloqueada: un doble clic o dos pestañas entregan una sola vez (el segundo
    // pedido espera al primero y ve que ya está entregado).
    $this->ordenes->transaction(function () use ($ordenId, $firma, $aclaracion, $usuarioId) {
      $orden = $this->ordenes->bloquear($ordenId);
      (new Validator())
        ->check($orden['estado'] === EstadoOrden::Finalizado->value, 'Para entregar el equipo, primero terminá el trabajo.')
        ->check($orden['entregado_en'] === null, 'Este equipo ya fue entregado el ' . format_date($orden['entregado_en'], 'd/m/Y \a \l\a\s H:i') . '.')
        ->validate();

      // La firma primero: si no vale (en blanco, dañada), el equipo no queda como entregado.
      if (trim((string) $firma) !== '') {
        $this->firmas->guardar($ordenId, 'entrega', (string) $firma, $aclaracion, $usuarioId);
      }

      $this->ordenes->setEntregado($ordenId, date('Y-m-d H:i:s'));
    });
    $this->auditor->registrar('entregar', 'orden', $ordenId, "Orden #{$ordenId}: el cliente retiró el equipo");
  }

  /** Cuánto tiempo después de entregarlo cualquiera puede deshacer la entrega (después, solo un administrador). */
  public const MINUTOS_PARA_DESHACER = 30;

  /**
   * Deshace una entrega registrada por error: el equipo vuelve a figurar como listo para retirar y
   * se borra la firma de entrega. La firma es la prueba de conformidad: pasado un rato, solo
   * un administrador puede deshacerla.
   */
  public function anular(int $ordenId, bool $administrador = false): void
  {
    $orden = $this->ordenService->obtener($ordenId);
    (new Validator())->check($orden['entregado_en'] !== null, 'Este equipo no figura como entregado.')->validate();
    (new Validator())->check(
      $administrador || strtotime((string) $orden['entregado_en']) >= time() - self::MINUTOS_PARA_DESHACER * 60,
      sprintf('Pasaron más de %d minutos desde la entrega: solo un administrador puede deshacerla.', self::MINUTOS_PARA_DESHACER),
    )->validate();

    $this->ordenes->setEntregado($ordenId, null);
    $this->firmas->eliminar($ordenId, 'entrega');
    $this->auditor->registrar('anular_entrega', 'orden', $ordenId, "Orden #{$ordenId}: se deshizo la entrega del equipo");
  }
}
