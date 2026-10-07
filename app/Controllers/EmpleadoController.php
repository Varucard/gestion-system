<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Estado;
use App\Exceptions\ValidationException;
use App\Repositories\EmpleadoRepository;
use App\Services\EmpleadoService;

final class EmpleadoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly EmpleadoService $service,
    private readonly EmpleadoRepository $empleados,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('empleados/index', ['title' => 'Empleados', 'empleados' => $this->empleados->all()]);
  }

  public function create(Request $request): void
  {
    $this->render('empleados/form', ['title' => 'Nuevo empleado', 'empleado' => null]);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->crear($request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/empleados/crear', $e, $request);
    }

    $this->success('Empleado registrado.');
    $this->redirect('/empleados');
  }

  public function edit(Request $request, int $id): void
  {
    $this->render('empleados/form', ['title' => 'Editar empleado', 'empleado' => $this->service->obtener($id)]);
  }

  public function update(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->actualizar($id, $request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors("/empleados/{$id}/editar", $e, $request);
    }

    $this->success('Empleado actualizado.');
    $this->redirect('/empleados');
  }

  public function toggle(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $estado = $this->service->alternarEstado($id);
    $this->success($estado === Estado::Activo ? 'Empleado activado.' : 'Empleado dado de baja.');
    $this->redirect('/empleados');
  }
}
