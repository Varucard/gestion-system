<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\EstadoOrden;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Services\EntregaService;
use App\Services\FirmaService;
use App\Services\OrdenService;
use App\Support\ImageUpload;

/** Entrega del equipo al cliente, con su firma de conformidad. */
final class EntregaController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly EntregaService $service,
    private readonly OrdenService $ordenService,
    private readonly OrdenRepository $ordenes,
    private readonly ClienteRepository $clientes,
    private readonly PagoRepository $pagos,
    private readonly FirmaService $firmas,
    private readonly Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  public function create(Request $request, int $id): void
  {
    $orden = $this->ordenService->obtener($id);
    if ($orden['estado'] !== EstadoOrden::Finalizado->value || $orden['entregado_en'] !== null) {
      $this->error($orden['entregado_en'] !== null ? 'Este equipo ya fue entregado.' : 'Para entregar el equipo, primero terminá el trabajo.');
      $this->redirect("/ordenes/{$id}");
    }

    $this->render('entregas/form', [
      'title' => "Entregar el equipo · Orden #{$id}",
      'orden' => $orden,
      'cliente' => $this->clientes->find((int) $orden['cliente_id']),
      'items' => $this->ordenes->items($id),
      'saldo' => round((float) $orden['total'] - $this->pagos->totalPagado($id), 2),
    ]);
  }

  public function store(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->entregar($id, $request->string('firma'), $request->string('firma_aclaracion'), $this->auth->id());
    } catch (ValidationException $e) {
      $this->backWithErrors("/ordenes/{$id}/entregar", $e, $request);
    }

    $this->hechoConDeshacer("Equipo entregado (orden #{$id}).", "ordenes/{$id}/entrega/anular");
    $this->redirect("/ordenes/{$id}");
  }

  /** "Deshacer" del aviso, o corrección de una entrega registrada por error. */
  public function anular(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->anular($id, $this->auth->esAdministrador());
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
      $this->redirect("/ordenes/{$id}");
    }
    $this->success("Se deshizo la entrega: el equipo de la orden #{$id} vuelve a figurar como listo para retirar.");
    $this->redirect("/ordenes/{$id}");
  }

  /** Imagen de una firma (al recibir o al entregar). */
  public function firma(Request $request, int $id, string $momento): void
  {
    if (!isset(FirmaService::MOMENTOS[$momento])) {
      throw new NotFoundException('Firma no encontrada.');
    }

    ImageUpload::enviar($this->firmas->ruta($id, $momento));
  }
}
