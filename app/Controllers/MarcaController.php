<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\MarcaRepository;
use App\Services\MarcaService;

final class MarcaController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly MarcaService $service,
    private readonly MarcaRepository $marcas,
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
      $this->success('Marca eliminada.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/marcas');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar($request->string('nombre'), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/marcas/{$id}/editar" : '/marcas', $e, $request);
    }

    $this->success($id ? 'Marca actualizada.' : 'Marca registrada.');
    $this->redirect('/marcas');
  }

  /** @param array<string, mixed>|null $marca */
  private function page(?array $marca): void
  {
    $this->render('marcas/index', [
      'title' => 'Marcas',
      'marca' => $marca,
      'marcas' => $this->marcas->all(),
    ]);
  }
}
