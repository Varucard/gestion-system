<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Estado;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\EquipoRepository;
use App\Services\ClienteService;
use App\Support\ImageUpload;

final class ClienteController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly ClienteService $service,
    private readonly ClienteRepository $clientes,
    private readonly EquipoRepository $equipos,
    private readonly OrdenRepository $ordenes,
    private readonly TurnoRepository $turnos,
    private readonly PagoRepository $pagos,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('clientes/index', ['title' => 'Clientes']);
  }

  public function datos(Request $request): void
  {
    $resultado = $this->clientes->paginar($request->queryAll());
    $saldos = $this->pagos->saldosDe(array_map(fn(array $c) => (int) $c['id'], $resultado['filas']));
    $this->tabla($resultado, 'clientes/_fila', 'c', ['saldos' => $saldos]);
  }

  /** Ficha del cliente: datos, equipos, órdenes, turnos y saldo. */
  public function show(Request $request, int $id): void
  {
    $this->render('clientes/show', [
      'title' => 'Ficha del cliente',
      'cliente' => $this->service->obtener($id),
      'equipos' => $this->equipos->porCliente($id),
      'ordenes' => $this->ordenes->porCliente($id),
      'turnos' => $this->turnos->porCliente($id),
      'saldo' => $this->pagos->saldosDe([$id])[$id] ?? 0.0,
    ]);
  }

  public function foto(Request $request, int $id): void
  {
    ImageUpload::enviar($this->service->rutaFoto($id));
  }

  public function subirFoto(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $archivo = $request->file('foto') ?? throw new ValidationException(['Seleccioná una imagen.']);
      $this->service->cambiarFoto($id, $archivo);
      $this->success('Foto actualizada.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/clientes/{$id}");
  }

  public function quitarFoto(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $this->service->quitarFoto($id);
    $this->success('Foto eliminada.');
    $this->redirect("/clientes/{$id}");
  }

  public function create(Request $request): void
  {
    // ?para=turno: viene de "Nuevo turno" (cliente que llama por teléfono): al guardar sigue con su equipo.
    $this->render('clientes/form', ['title' => 'Nuevo cliente', 'cliente' => null, 'paraTurno' => $request->query('para') === 'turno']);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);
    $paraTurno = $request->string('para') === 'turno';

    try {
      $id = $this->service->crear($request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/clientes/crear' . ($paraTurno ? '?para=turno' : ''), $e, $request);
    }

    $this->success('Cliente registrado correctamente.');
    $this->redirect($paraTurno ? "/equipos/crear?cliente_id={$id}&para=turno" : "/clientes/{$id}");
  }

  public function edit(Request $request, int $id): void
  {
    $this->render('clientes/form', [
      'title' => 'Editar cliente',
      'cliente' => $this->service->obtener($id),
    ]);
  }

  public function update(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->actualizar($id, $request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors("/clientes/{$id}/editar", $e, $request);
    }

    $this->success('Cliente actualizado correctamente.');
    $this->redirect("/clientes/{$id}");
  }

  public function toggle(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $estado = $this->service->alternarEstado($id);
    // Se hace sin preguntar y se ofrece deshacer (el mismo POST vuelve al estado anterior).
    $mensaje = $estado === Estado::Activo ? 'Cliente activado.' : 'Cliente desactivado: ya no aparece al dar turnos ni al abrir órdenes. No se borró nada.';
    $request->string('deshaciendo') === '1' ? $this->success($mensaje) : $this->hechoConDeshacer($mensaje, "clientes/{$id}/estado");
    $this->redirect('/clientes');
  }

  public function destroy(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->eliminar($id);
      $this->success('Cliente eliminado.');
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/clientes');
  }
}
