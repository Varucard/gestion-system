<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ProveedorRepository;
use App\Services\ProveedorService;

final class ProveedorController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly ProveedorService $service,
    private readonly ProveedorRepository $proveedores,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('proveedores/index', ['title' => 'Proveedores', 'proveedores' => $this->proveedores->all()]);
  }

  public function create(Request $request): void
  {
    $this->render('proveedores/form', ['title' => 'Nuevo proveedor', 'proveedor' => null]);
  }

  public function edit(Request $request, int $id): void
  {
    $this->render('proveedores/form', ['title' => 'Editar proveedor', 'proveedor' => $this->service->obtener($id)]);
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
      $this->success('Proveedor eliminado.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/proveedores');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar($request->all(), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/proveedores/{$id}/editar" : '/proveedores/crear', $e, $request);
    }

    $this->success($id ? 'Proveedor actualizado.' : 'Proveedor registrado.');
    $this->redirect('/proveedores');
  }
}
