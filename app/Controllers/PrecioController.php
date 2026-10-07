<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ProveedorRepository;
use App\Services\PrecioService;

/** Actualización masiva de precios (solo administradores). */
final class PrecioController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly PrecioService $precios,
    private readonly ProveedorRepository $proveedores,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->pantalla(null);
  }

  public function vistaPrevia(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->pantalla($this->precios->vistaPrevia($request->all()));
    } catch (ValidationException $e) {
      $this->backWithErrors('/precios', $e, $request);
    }
  }

  public function aplicar(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $cantidad = $this->precios->aplicar($request->all());
      $this->success("Se actualizaron {$cantidad} precios.");
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/precios');
  }

  /** @param array{parametros: array<string, mixed>, items: list<array<string, mixed>>}|null $vista */
  private function pantalla(?array $vista): void
  {
    $this->render('precios/index', [
      'title' => 'Actualizar precios',
      'proveedores' => $this->proveedores->activos(),
      'vista' => $vista,
    ]);
  }
}
