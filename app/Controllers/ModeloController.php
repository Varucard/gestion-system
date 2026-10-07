<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\MarcaRepository;
use App\Repositories\ModeloRepository;
use App\Services\ModeloService;

final class ModeloController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly ModeloService $service,
    private readonly ModeloRepository $modelos,
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
      $this->success('Modelo eliminado.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/modelos');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar((int) $request->int('marca_id'), $request->string('nombre'), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/modelos/{$id}/editar" : '/modelos', $e, $request);
    }

    $this->success($id ? 'Modelo actualizado.' : 'Modelo registrado.');
    $this->redirect('/modelos');
  }

  /** @param array<string, mixed>|null $modelo */
  private function page(?array $modelo): void
  {
    $this->render('modelos/index', [
      'title' => 'Modelos',
      'modelo' => $modelo,
      'modelos' => $this->modelos->all(),
      'marcas' => $this->marcas->all(),
    ]);
  }
}
