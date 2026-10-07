<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Estado;
use App\Enums\TipoEquipo;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\MarcaRepository;
use App\Repositories\ModeloRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\EquipoRepository;
use App\Services\EquipoService;
use App\Support\ImageUpload;

final class EquipoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly EquipoService $service,
    private readonly EquipoRepository $equipos,
    private readonly ClienteRepository $clientes,
    private readonly MarcaRepository $marcas,
    private readonly ModeloRepository $modelos,
    private readonly OrdenRepository $ordenes,
    private readonly TurnoRepository $turnos,
    private readonly Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('equipos/index', ['title' => 'Equipos']);
  }

  public function datos(Request $request): void
  {
    $this->tabla($this->equipos->paginar($request->queryAll()), 'equipos/_fila', 'v');
  }

  /** Ficha del equipo: datos, imágenes, órdenes y turnos. */
  public function show(Request $request, int $id): void
  {
    $this->render('equipos/show', [
      'title' => 'Ficha del equipo',
      'equipo' => $this->service->obtener($id),
      'imagenes' => $this->equipos->imagenes($id),
      'ordenes' => $this->ordenes->porEquipo($id),
      'turnos' => $this->turnos->porEquipo($id),
      'proximoMantenimiento' => $this->equipos->proximoMantenimiento($id),
    ]);
  }

  public function imagen(Request $request, int $id, int $imagenId): void
  {
    ImageUpload::enviar($this->service->rutaImagen($id, $imagenId));
  }

  public function subirImagen(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $archivo = $request->file('imagen') ?? throw new ValidationException(['Seleccioná una imagen.']);
      $this->service->agregarImagen($id, $archivo, $request->string('descripcion'), $this->auth->id());
      $this->success('Imagen agregada.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/equipos/{$id}");
  }

  public function eliminarImagen(Request $request, int $imagenId): void
  {
    $this->verifyCsrf($request);

    $equipoId = $this->service->eliminarImagen($imagenId);
    $this->success('Imagen eliminada.');
    $this->redirect("/equipos/{$equipoId}");
  }

  public function create(Request $request): void
  {
    // Permite llegar desde la ficha del cliente con el cliente ya elegido.
    $this->form('Registrar equipo', ($c = $request->int('cliente_id')) ? ['cliente_id' => $c] : null, true);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $id = $this->service->crear($request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/equipos/crear', $e, $request);
    }

    $this->success('Equipo registrado correctamente.');
    $this->redirect("/equipos/{$id}");
  }

  public function edit(Request $request, int $id): void
  {
    $this->form('Editar equipo', $this->service->obtener($id));
  }

  public function update(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->actualizar($id, $request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors("/equipos/{$id}/editar", $e, $request);
    }

    $this->success('Equipo actualizado correctamente.');
    $this->redirect("/equipos/{$id}");
  }

  public function toggle(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $estado = $this->service->alternarEstado($id);
    $this->success($estado === Estado::Activo ? 'Equipo activado.' : 'Equipo desactivado.');
    $this->redirect('/equipos');
  }

  /** JSON: modelos de una marca (cascada del formulario). */
  public function modelosPorMarca(Request $request, int $marcaId): void
  {
    $this->json($this->modelos->porMarca($marcaId));
  }

  /** JSON: equipos activos de un cliente. */
  public function porCliente(Request $request, int $clienteId): void
  {
    $equipos = array_map(fn(array $e) => [
      'id' => (int) $e['id'],
      'texto' => equipo_texto($e),
    ], $this->equipos->activosPorCliente($clienteId));

    $this->json($equipos);
  }

  /** @param array<string, mixed>|null $equipo */
  private function form(string $title, ?array $equipo, bool $nuevo = false): void
  {
    $marcaId = (int) old('marca_id', $equipo['marca_id'] ?? 0);

    $this->render('equipos/form', [
      'title' => $title,
      'equipo' => $nuevo ? null : $equipo,
      'clienteSugerido' => $nuevo ? (int) ($equipo['cliente_id'] ?? 0) : 0,
      'clientes' => $this->clientes->all(),
      'marcas' => $this->marcas->all(),
      'modelos' => $marcaId > 0 ? $this->modelos->porMarca($marcaId) : [],
      'tipos' => TipoEquipo::cases(),
    ]);
  }
}
