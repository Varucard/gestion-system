<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\ComboRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Services\ComboService;

final class ComboController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly ComboService $service,
    private readonly ComboRepository $combos,
    private readonly ServicioRepository $servicios,
    private readonly RepuestoRepository $repuestos,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('combos/index', ['title' => 'Combos de servicios', 'combos' => $this->combos->all()]);
  }

  public function create(Request $request): void
  {
    $this->form('Nuevo combo', null);
  }

  public function edit(Request $request, int $id): void
  {
    $this->form('Editar combo', $this->service->obtener($id));
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
    $this->service->eliminar($id);
    $this->success('Combo eliminado.');
    $this->redirect('/combos');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);

    $items = ['servicio' => [], 'repuesto' => []];
    foreach (['servicio', 'repuesto'] as $tipo) {
      $cantidades = (array) $request->input("cantidad_{$tipo}", []);
      foreach ($request->intList("{$tipo}_id") as $itemId) {
        $items[$tipo][$itemId] = (string) ($cantidades[$itemId] ?? '1');
      }
    }

    try {
      $this->service->guardar([...$request->all(), 'items' => $items], $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/combos/{$id}/editar" : '/combos/crear', $e, $request);
    }

    $this->success($id ? 'Combo actualizado.' : 'Combo creado.');
    $this->redirect('/combos');
  }

  /** @param array<string, mixed>|null $combo */
  private function form(string $title, ?array $combo): void
  {
    $detalle = ['servicio' => [], 'repuesto' => []];
    foreach ($combo['items'] ?? [] as $item) {
      $tipo = $item['repuesto_id'] !== null ? 'repuesto' : 'servicio';
      $detalle[$tipo][(int) $item["{$tipo}_id"]] = ['cantidad' => (float) $item['cantidad']];
    }

    $this->render('combos/form', [
      'title' => $title,
      'combo' => $combo,
      'detalle' => $detalle,
      'servicios' => $this->servicios->all(),
      'repuestos' => $this->repuestos->all(),
    ]);
  }
}
