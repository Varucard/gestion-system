<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ServicioRepository;
use App\Services\ServicioService;

final class ServicioController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly ServicioService $service,
    private readonly ServicioRepository $servicios,
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
      $this->success('Servicio eliminado.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/servicios');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar($request->all(), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/servicios/{$id}/editar" : '/servicios', $e, $request);
    }

    $this->success($id ? 'Servicio actualizado.' : 'Servicio registrado.');
    $this->redirect('/servicios');
  }

  /** @param array<string, mixed>|null $servicio */
  private function page(?array $servicio): void
  {
    $this->render('servicios/index', [
      'title' => 'Servicios',
      'servicio' => $servicio,
      'servicios' => $this->servicios->all(),
    ]);
  }
}
