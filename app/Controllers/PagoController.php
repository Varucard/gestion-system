<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\PagoRepository;
use App\Services\PagoService;

final class PagoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly PagoService $service,
    private readonly PagoRepository $pagos,
    private readonly Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  public function store(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->registrar($id, $request->all(), $this->auth->id());
      $saldo = $this->service->saldo($id);
      $this->success($saldo > 0 ? 'Pago registrado. Saldo pendiente: $ ' . money($saldo) . '.' : 'Pago registrado. La orden quedó saldada.');
    } catch (ValidationException $e) {
      foreach ($e->errors() as $mensaje) {
        $this->error($mensaje);
      }
      $this->session->keepInput($request->all());
    }

    $this->redirect("/ordenes/{$id}");
  }

  public function destroy(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $ordenId = $this->service->anular($id);
    $this->success('Pago anulado.');
    $this->redirect("/ordenes/{$ordenId}");
  }

  public function deudores(Request $request): void
  {
    $this->render('clientes/deudores', ['title' => 'Clientes deudores', 'deudores' => $this->pagos->deudores()]);
  }
}
