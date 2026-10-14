<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\TipoEquipo;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\EmpleadoRepository;
use App\Repositories\EquipoRepository;
use App\Repositories\MarcaRepository;
use App\Repositories\ModeloRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Services\EquipoService;
use App\Services\FirmaService;
use App\Services\RecepcionService;

/** "Llegó un equipo": del DNI del cliente a la orden abierta, en una sola pantalla. */
final class RecepcionController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly RecepcionService $service,
    private readonly TurnoRepository $turnos,
    private readonly EquipoRepository $equipos,
    private readonly ClienteRepository $clientes,
    private readonly MarcaRepository $marcas,
    private readonly ModeloRepository $modelos,
    private readonly EmpleadoRepository $empleados,
    private readonly OrdenRepository $ordenes,
    private readonly EquipoService $equipoService,
    private readonly FirmaService $firmas,
    private readonly Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  public function create(Request $request): void
  {
    // Desde un turno o desde la ficha del equipo: el cliente y el equipo ya se conocen.
    $turno = ($turnoId = $request->int('turno_id')) ? $this->turnos->find($turnoId) : null;
    $equipoPrevio = ($id = (int) ($turno['equipo_id'] ?? $request->int('equipo_id'))) ? $this->equipos->find($id) : null;
    $clientePrevio = $equipoPrevio ? $this->clientes->find((int) $equipoPrevio['cliente_id']) : null;

    $dni = (string) old('dni', $clientePrevio['dni'] ?? $request->query('dni', ''));
    $cliente = $dni !== '' ? $this->service->buscarDni($dni) : null;
    $marcaId = (int) old('marca_id', 0);

    $this->render('recepcion/form', [
      'title' => 'Llegó un equipo',
      'turno' => $turno,
      'dni' => $dni,
      'cliente' => $cliente,
      'equiposCliente' => $cliente ? $this->service->equiposDe((int) $cliente['id']) : [],
      'equipoElegido' => (string) old('equipo_id', $equipoPrevio['id'] ?? ''),
      'falla' => $turno['descripcion'] ?? '',
      'tipos' => TipoEquipo::cases(),
      'marcas' => $this->marcas->all(),
      'modelos' => $marcaId > 0 ? $this->modelos->porMarca($marcaId) : [],
      'tecnicos' => $this->empleados->activos(),
    ]);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $ordenId = $this->service->recibir($request->all());
    } catch (ValidationException $e) {
      $turnoId = $request->int('turno_id');
      $this->backWithErrors('/recepcion' . ($turnoId ? "?turno_id={$turnoId}" : ''), $e, $request);
    }

    // Las fotos y la firma van después de abrir la orden: si una falla, el equipo igual queda
    // recibido y se avisa qué faltó (se puede agregar desde la orden).
    $faltas = [...$this->guardarFotos($ordenId, $request), ...$this->guardarFirma($ordenId, $request)];
    $faltas === []
      ? $this->success("Equipo recibido: se abrió la orden #{$ordenId}. Cuando sepas qué hay que hacer, cargá los servicios y repuestos.")
      : $this->aviso("El equipo quedó recibido (orden #{$ordenId}), pero " . implode(' ', array_map(fn($f, $i) => $i === 0 ? $f : ucfirst($f), $faltas, array_keys($faltas))) . ' Podés agregarlo desde la orden.');
    $this->redirect("/ordenes/{$ordenId}");
  }

  /** Más fotos del equipo, desde la ficha de la orden. */
  public function fotos(Request $request, int $id): void
  {
    $this->verifyCsrf($request);
    $this->ordenes->find($id) ?? throw new NotFoundException('Orden no encontrada.');

    $faltas = $request->files('fotos') === [] ? ['No elegiste ninguna foto.'] : $this->guardarFotos($id, $request);
    $faltas === [] ? $this->success('Fotos agregadas a la orden.') : $this->error(implode(' ', $faltas));
    $this->redirect("/ordenes/{$id}#como_llego");
  }

  /** Firma de la conformidad al recibir, si no se hizo en "Llegó un equipo". */
  public function firmarForm(Request $request, int $id): void
  {
    $orden = $this->ordenes->find($id) ?? throw new NotFoundException('Orden no encontrada.');
    if (isset($this->firmas->deOrden($id)['recepcion'])) {
      $this->aviso('El cliente ya firmó cómo llegó el equipo: la firma está en «Cómo llegó».');
      $this->redirect("/ordenes/{$id}#como_llego");
    }

    $this->render('recepcion/firmar', ['title' => "Conformidad al recibir · Orden #{$id}", 'orden' => $orden]);
  }

  public function firmar(Request $request, int $id): void
  {
    $this->verifyCsrf($request);
    $this->ordenes->find($id) ?? throw new NotFoundException('Orden no encontrada.');

    try {
      $this->firmas->guardar($id, 'recepcion', $request->string('firma'), $request->string('firma_aclaracion'), $this->auth->id());
    } catch (ValidationException $e) {
      $this->backWithErrors("/ordenes/{$id}/firmar", $e, $request);
    }

    $this->success('Quedó firmada la conformidad con los accesorios y el estado en que llegó el equipo.');
    $this->redirect("/ordenes/{$id}#como_llego");
  }

  /** Cuántas fotos se aceptan de una vez (cada una ya viene achicada por el navegador). */
  public const MAX_FOTOS = 10;

  /** @return list<string> lo que no se pudo guardar */
  private function guardarFotos(int $ordenId, Request $request): array
  {
    $fotos = $request->files('fotos');
    if ($fotos === []) {
      return [];
    }

    $equipoId = (int) $this->ordenes->find($ordenId)['equipo_id'];
    $faltas = count($fotos) > self::MAX_FOTOS ? [sprintf('se guardaron las primeras %d fotos.', self::MAX_FOTOS)] : [];
    foreach (array_slice($fotos, 0, self::MAX_FOTOS) as $i => $foto) {
      try {
        $this->equipoService->agregarImagen($equipoId, $foto, "Orden #{$ordenId}", $this->auth->id(), $ordenId);
      } catch (ValidationException $e) {
        $faltas[] = sprintf('la foto %d no se guardó (%s)', $i + 1, rtrim(lcfirst($e->getMessage()), '.')) . '.';
      }
    }

    return $faltas;
  }

  /** @return list<string> */
  private function guardarFirma(int $ordenId, Request $request): array
  {
    if (trim($request->string('firma')) === '') {
      return [];
    }

    try {
      $this->firmas->guardar($ordenId, 'recepcion', $request->string('firma'), $request->string('firma_aclaracion'), $this->auth->id());

      return [];
    } catch (ValidationException $e) {
      return ['la firma no se guardó: ' . rtrim(lcfirst($e->getMessage()), '.') . '.'];
    }
  }

  /**
   * JSON: ¿el DNI ya es de un cliente? Si lo es, sus equipos activos con las órdenes que
   * siguen en el local. Lo mínimo para reconocerlos: sin teléfono ni otros datos personales.
   */
  public function cliente(Request $request): void
  {
    $c = $this->service->buscarDni((string) $request->query('dni', ''));
    if ($c === null) {
      $this->json(null);
    }

    $this->json([
      'id' => (int) $c['id'],
      'nombre' => "{$c['apellido']}, {$c['nombre']}",
      'activo' => $c['estado'] === 'activo',
      'equipos' => array_map(fn(array $e) => [
        'id' => (int) $e['id'],
        'texto' => equipo_texto($e),
        'url' => url("equipos/{$e['id']}"),
        'abiertas' => array_map(fn(array $o) => [
          'id' => (int) $o['id'], 'url' => url("ordenes/{$o['id']}"), 'lista' => $o['estado'] === \App\Enums\EstadoOrden::Finalizado->value,
        ], $e['abiertas']),
      ], $this->service->equiposDe((int) $c['id'])),
    ]);
  }
}
