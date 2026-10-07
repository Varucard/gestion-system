<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Core\Auth;
use App\Repositories\ProveedorRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\StockRepository;
use App\Services\RepuestoService;
use App\Services\StockService;

final class RepuestoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly RepuestoService $service,
    private readonly RepuestoRepository $repuestos,
    private readonly ProveedorRepository $proveedores,
    private readonly StockRepository $movimientos,
    private readonly StockService $stock,
    private readonly Auth $auth,
    private readonly \App\Services\ConfiguracionService $configuracion,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->page(null);
  }

  public function edit(Request $request, int $id): void
  {
    $this->page($this->service->obtener($id));
  }

  public function store(Request $request): void
  {
    $this->save($request, null);
  }

  public function update(Request $request, int $id): void
  {
    $this->save($request, $id);
  }

  public function destroy(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->eliminar($id);
      $this->success('Repuesto eliminado.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/repuestos');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar($request->all(), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/repuestos/{$id}/editar" : '/repuestos', $e, $request);
    }

    $this->success($id ? 'Repuesto actualizado.' : 'Repuesto registrado.');
    $this->redirect('/repuestos');
  }

  /** @param array<string, mixed>|null $repuesto */
  private function page(?array $repuesto): void
  {
    $this->render('repuestos/index', [
      'title' => 'Repuestos',
      'repuesto' => $repuesto,
      'repuestos' => $this->repuestos->all(),
      'proveedores' => $this->proveedores->activos(),
      'margen' => (float) $this->configuracion->seccion('stock')['margen_sugerido'],
    ]);
  }

  public function stock(Request $request, int $id): void
  {
    $this->render('repuestos/stock', [
      'title' => 'Stock de repuesto',
      'repuesto' => $this->service->obtener($id),
      'movimientos' => $this->movimientos->historial($id),
      'proveedores' => $this->proveedores->activos(),
      'margen' => (float) $this->configuracion->seccion('stock')['margen_sugerido'],
    ]);
  }

  public function ingresar(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $stock = $this->stock->ingresar(
        $id, $request->string('cantidad'), $request->int('proveedor_id') ?: null, $request->string('motivo'), $this->auth->id(),
        $request->string('costo_unitario'), (bool) $request->input('actualizar_precio'),
      );
      $this->success('Ingreso registrado. Stock actual: ' . qty($stock) . '.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/repuestos/{$id}/stock");
  }

  public function ajustar(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $stock = $this->stock->ajustar($id, $request->string('stock_real'), $request->string('motivo'), $this->auth->id());
      $this->success('Stock ajustado a ' . qty($stock) . '.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/repuestos/{$id}/stock");
  }
}
