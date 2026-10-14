<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTurno;
use App\Exceptions\NotFoundException;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Repositories\EmpleadoRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Repositories\EquipoRepository;
use App\Support\Validator;

final class OrdenService
{
  public function __construct(
    private readonly OrdenRepository $ordenes,
    private readonly EquipoRepository $equipos,
    private readonly ServicioRepository $servicios,
    private readonly RepuestoRepository $repuestos,
    private readonly StockService $stock,
    private readonly PagoRepository $pagos,
    private readonly EmpleadoRepository $empleados,
    private readonly TurnoRepository $turnos,
    private readonly ConfiguracionService $configuracion,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->ordenes->find($id) ?? throw new NotFoundException('Orden no encontrada.');
  }

  public static function editable(EstadoOrden $estado): bool
  {
    return $estado === EstadoOrden::Pendiente || $estado === EstadoOrden::EnProceso;
  }

  /** Cómo va un repuesto en la orden: del negocio con su precio, a costo (sin margen) o lo trae el cliente. */
  public const MODOS_REPUESTO = ['negocio' => 'Del negocio', 'costo' => 'A costo (sin ganancia)', 'cliente' => 'Lo trae el cliente'];

  /** Largo máximo de la descripción de una pieza que trae el cliente y no está en el catálogo. */
  public const DESCRIPCION_MAXIMA = 150;

  /**
   * Crea o actualiza una orden.
   *
   * Cada lista de ítems puede ser una lista de ids (cantidad 1) o un mapa
   * id => ['cantidad' => x, 'precio' => y]. Si no se indica precio, se usa el
   * que el ítem ya tenía en la orden o, si es nuevo, el del catálogo: así los
   * precios quedan congelados aunque cambie el catálogo.
   *
   * Los repuestos admiten además 'modo' (ver MODOS_REPUESTO). "A costo" toma el precio de
   * costo del repuesto al marcarlo (y lo conserva mientras siga a costo); "lo trae el
   * cliente" va sin precio. Las piezas que trae el cliente y no están en el catálogo van en
   * $piezasCliente: lista de ['descripcion' => …, 'cantidad' => …].
   *
   * @param array<int, mixed> $servicios
   * @param array<int, mixed> $repuestos
   * @param array<string, mixed> $detalle falla_reportada, accesorios, estado_ingreso, diagnostico, trabajo_realizado,
   *                                      notas_internas, proximo_mantenimiento_fecha, turno_id (solo al crear)
   * @param list<array{descripcion?: string, cantidad?: string}> $piezasCliente
   */
  public function guardar(
    int $equipoId,
    array $servicios,
    array $repuestos,
    ?int $id = null,
    ?int $tecnicoId = null,
    array $detalle = [],
    array $piezasCliente = [],
  ): int {
    $servicios = self::normalizarItems($servicios);
    $repuestos = self::normalizarItems($repuestos);
    $piezasCliente = self::normalizarPiezas($piezasCliente);
    $previos = ['servicio' => [], 'repuesto' => []];

    if ($id !== null) {
      $actual = $this->obtener($id);
      (new Validator())
        ->check(self::editable(EstadoOrden::from($actual['estado'])), 'Solo se pueden editar órdenes recibidas o en reparación.')
        ->validate();

      $itemsPrevios = $this->ordenes->items($id);
      foreach ($itemsPrevios as $item) {
        if ($item['descripcion'] !== null) {
          continue;
        }
        $tipo = $item['repuesto_id'] !== null ? 'repuesto' : 'servicio';
        $previos[$tipo][(int) $item["{$tipo}_id"]] = ['precio' => (float) $item['precio_unitario'], 'modo' => self::modo($item)];
      }
    }

    $equipo = $equipoId > 0 ? $this->equipos->find($equipoId) : null;
    $mantieneEquipo = isset($actual) && (int) $actual['equipo_id'] === $equipoId;
    $preciosServicios = $this->servicios->precios(array_keys($servicios));
    $preciosRepuestos = $this->repuestos->precios(array_keys($repuestos));
    // Lo que se pasa a costo en este guardado (lo que ya estaba a costo conserva su precio).
    $nuevosACosto = array_keys(array_filter(
      $repuestos,
      fn(array $r, int $rid) => $r['modo'] === 'costo' && ($previos['repuesto'][$rid]['modo'] ?? null) !== 'costo',
      ARRAY_FILTER_USE_BOTH,
    ));
    $costos = $this->repuestos->costos($nuevosACosto);
    $sinCosto = array_map(
      fn(int $rid) => $this->repuestos->find($rid)['nombre'] ?? "#{$rid}",
      array_keys(array_filter($costos, fn(?float $c) => $c === null)),
    );
    $valoresValidos = fn(array $items) => array_reduce(
      $items,
      fn(bool $ok, array $i) => $ok && $i['cantidad'] !== null && $i['cantidad'] > 0 && ($i['precio'] === null || $i['precio'] >= 0),
      true
    );

    (new Validator())
      ->check($equipo !== null && ($equipo['estado'] === 'activo' || $mantieneEquipo), 'Seleccioná un equipo activo.')
      ->check($this->tecnicoValido($tecnicoId, $actual ?? null), 'Seleccioná un técnico activo.')
      ->check(count($preciosServicios) === count($servicios), 'Alguno de los servicios seleccionados no existe.')
      ->check(count($preciosRepuestos) === count($repuestos), 'Alguno de los repuestos seleccionados no existe.')
      ->check($valoresValidos($servicios) && $valoresValidos($repuestos), 'Las cantidades deben ser mayores a 0 (escribilas sin punto, o con coma si llevan decimales: 1,25) y los precios no pueden ser negativos.')
      ->check(array_filter($repuestos, fn(array $r) => !isset(self::MODOS_REPUESTO[$r['modo']])) === [], 'Elegí si cada repuesto es del negocio, a costo o lo trae el cliente.')
      ->check($sinCosto === [], sprintf(
        'Para cobrar a costo hace falta el precio de costo, y no lo tiene: %s. Cargalo en el repuesto (o con un ingreso de stock) o cobralo con el precio normal.',
        implode(', ', $sinCosto),
      ))
      ->check(array_filter($piezasCliente, fn(array $p) => $p['descripcion'] === '' || mb_strlen($p['descripcion']) > self::DESCRIPCION_MAXIMA) === [],
        sprintf('Escribí qué repuesto trae el cliente (hasta %d caracteres).', self::DESCRIPCION_MAXIMA))
      ->check(array_filter($piezasCliente, fn(array $p) => $p['cantidad'] === null || $p['cantidad'] <= 0) === [],
        'La cantidad de cada repuesto que trae el cliente debe ser mayor a 0.')
      ->validate();

    $items = [];
    foreach ($servicios as $sid => $s) {
      $precio = $s['precio'] ?? $previos['servicio'][$sid]['precio'] ?? $preciosServicios[$sid];
      $items[] = OrdenItem::servicio($sid, $precio, $s['cantidad']);
    }
    foreach ($repuestos as $rid => $r) {
      // El precio anterior solo vale si el repuesto sigue en el mismo modo.
      $previo = ($previos['repuesto'][$rid]['modo'] ?? null) === $r['modo'] ? $previos['repuesto'][$rid]['precio'] : null;
      $items[] = match ($r['modo']) {
        'cliente' => OrdenItem::provistoPorCliente($rid, null, $r['cantidad']),
        // A costo no se edita a mano: queda el costo del momento en que se marcó.
        'costo' => OrdenItem::repuesto($rid, $previo ?? $costos[$rid], $r['cantidad'], aCosto: true),
        default => OrdenItem::repuesto($rid, $r['precio'] ?? $previo ?? $preciosRepuestos[$rid], $r['cantidad']),
      };
    }
    foreach ($piezasCliente as $p) {
      $items[] = OrdenItem::provistoPorCliente(null, $p['descripcion'], $p['cantidad']);
    }

    $datos = $this->validarDetalle($detalle, $equipoId, $id);
    $orden = new Orden(
      $equipoId, $items, id: $id, tecnicoId: $tecnicoId,
      fallaReportada: $datos['falla_reportada'], accesorios: $datos['accesorios'], estadoIngreso: $datos['estado_ingreso'],
      diagnostico: $datos['diagnostico'], trabajoRealizado: $datos['trabajo_realizado'], notasInternas: $datos['notas_internas'],
      proximoMantenimientoFecha: $datos['proximo_mantenimiento_fecha'], turnoId: $datos['turno_id'],
    );
    (new Validator())
      ->check($orden->total() <= Validator::IMPORTE_MAXIMO, sprintf('El total de la orden supera el máximo admitido ($ %s).', money(Validator::IMPORTE_MAXIMO)))
      ->validate();

    // Si el cliente ya había respondido el presupuesto y cambian los ítems o los precios, la
    // respuesta deja de valer: lo que aceptó (o rechazó) ya no es lo que dice la orden.
    $anulaRespuesta = isset($actual, $itemsPrevios) && $actual['presupuesto_respuesta'] !== null
      && self::firmaItems($itemsPrevios) !== self::firmaItems(array_map(fn(OrdenItem $i) => [
        'servicio_id' => $i->servicioId, 'repuesto_id' => $i->repuestoId, 'descripcion' => $i->descripcion,
        'cantidad' => $i->cantidad, 'precio_unitario' => $i->precioUnitario, 'provisto_cliente' => $i->provistoCliente,
      ], $items));

    $guardada = $this->ordenes->transaction(function () use ($orden, $anulaRespuesta) {
      // Con la orden bloqueada, un pago simultáneo no puede dejar el total por debajo de lo pagado.
      if ($orden->id !== null) {
        $this->ordenes->bloquear($orden->id);
        $pagado = $this->pagos->totalPagado($orden->id);
        (new Validator())
          ->check($orden->total() >= $pagado, sprintf('El total no puede quedar por debajo de lo ya pagado ($ %s).', money($pagado)))
          ->validate();
      }

      $guardada = $this->ordenes->save($orden);

      if ($anulaRespuesta) {
        $this->ordenes->anularRespuestaPresupuesto($guardada);
      }

      // La orden nace de un turno: el turno queda como realizado.
      if ($orden->turnoId !== null) {
        $this->turnos->setEstado($orden->turnoId, EstadoTurno::Realizado);
      }

      return $guardada;
    });
    $this->auditor->registrar(
      $id === null ? 'crear' : 'editar',
      'orden',
      $guardada,
      ($id === null ? 'Orden creada' : 'Orden editada') . " #{$guardada} por $ " . money($orden->total()),
      $id !== null && isset($actual) && (float) $actual['total'] !== $orden->total() ? ['total_antes' => (float) $actual['total'], 'total_despues' => $orden->total()] : [],
    );
    if ($anulaRespuesta) {
      $this->auditor->registrar(
        'presupuesto_anulado',
        'orden',
        $guardada,
        "Orden #{$guardada}: se modificó después de que el cliente {$actual['presupuesto_respuesta']} el presupuesto; la respuesta quedó sin efecto",
        ['respuesta' => $actual['presupuesto_respuesta'], 'total_respondido' => (float) $actual['total'], 'total_nuevo' => $orden->total()],
      );
    }

    return $guardada;
  }

  /**
   * Representación comparable de los ítems (tipo, id o descripción, si lo trae el cliente,
   * cantidad y precio), sin importar el orden. Pasar un repuesto a costo cambia su precio,
   * así que también cuenta como cambio.
   *
   * @param list<array<string, mixed>> $items
   */
  private static function firmaItems(array $items): string
  {
    $firma = array_map(fn(array $i) => sprintf(
      '%s:%s:%d:%.2f:%.2f',
      $i['servicio_id'] !== null ? 's' : ($i['repuesto_id'] !== null ? 'r' : 'p'),
      $i['servicio_id'] ?? $i['repuesto_id'] ?? $i['descripcion'],
      (int) (bool) $i['provisto_cliente'],
      (float) $i['cantidad'],
      (float) $i['precio_unitario'],
    ), $items);
    sort($firma);

    return implode('|', $firma);
  }

  /**
   * @param array<string, mixed> $detalle
   * @return array{falla_reportada: ?string, accesorios: ?string, estado_ingreso: ?string, diagnostico: ?string,
   *               trabajo_realizado: ?string, notas_internas: ?string, proximo_mantenimiento_fecha: ?string, turno_id: ?int}
   */
  private function validarDetalle(array $detalle, int $equipoId, ?int $id): array
  {
    $texto = fn(string $clave) => Validator::nullable((string) ($detalle[$clave] ?? ''));

    $accesorios = $texto('accesorios');
    $proximaFecha = $texto('proximo_mantenimiento_fecha');
    $turnoId = $id === null ? ((int) ($detalle['turno_id'] ?? 0) ?: null) : null;
    $turno = $turnoId !== null ? $this->turnos->find($turnoId) : null;
    $estadoIngreso = $texto('estado_ingreso');

    (new Validator())
      ->check($accesorios === null || Validator::largo($accesorios, 1, 255), 'Los accesorios no pueden superar los 255 caracteres.', 'accesorios')
      ->check($estadoIngreso === null || mb_strlen($estadoIngreso) <= 1000, 'El estado en que llegó el equipo puede tener hasta 1000 caracteres.', 'estado_ingreso')
      ->check($proximaFecha === null || (Validator::fecha($proximaFecha) && $proximaFecha > date('Y-m-d')), 'La fecha del próximo mantenimiento debe ser futura.', 'proximo_mantenimiento_fecha')
      ->check($turnoId === null || ($turno !== null && (int) $turno['equipo_id'] === $equipoId), 'El turno no corresponde al equipo de la orden.')
      // Un turno cancelado, ausente o que ya originó una orden no abre otra.
      ->check($turno === null || in_array($turno['estado'], [EstadoTurno::Pendiente->value, EstadoTurno::Confirmado->value], true),
        'Ese turno ya no está pendiente (fue cancelado o ya se recibió el equipo). Recibilo sin turno, desde "Llegó un equipo".')
      ->check($turnoId === null || !$this->ordenes->existeConTurno($turnoId), 'Ese turno ya tiene una orden abierta.')
      ->validate();

    return [
      'falla_reportada' => $texto('falla_reportada'),
      'accesorios' => $accesorios,
      'estado_ingreso' => $estadoIngreso,
      'diagnostico' => $texto('diagnostico'),
      'trabajo_realizado' => $texto('trabajo_realizado'),
      'notas_internas' => $texto('notas_internas'),
      'proximo_mantenimiento_fecha' => $proximaFecha,
      'turno_id' => $turnoId,
    ];
  }

  /** Sin técnico, uno activo, o el que la orden ya tenía asignado (aunque hoy esté inactivo). */
  private function tecnicoValido(?int $tecnicoId, ?array $ordenActual): bool
  {
    if ($tecnicoId === null) {
      return true;
    }

    $tecnico = $this->empleados->find($tecnicoId);

    return $tecnico !== null
      && ($tecnico['estado'] === 'activo' || (int) ($ordenActual['tecnico_id'] ?? 0) === $tecnicoId);
  }

  /** Modo de un ítem guardado (ver MODOS_REPUESTO). */
  public static function modo(array $item): string
  {
    return match (true) {
      (bool) $item['provisto_cliente'] => 'cliente',
      (bool) $item['a_costo'] => 'costo',
      default => 'negocio',
    };
  }

  /**
   * @param list<array<string, mixed>> $piezas
   * @return list<array{descripcion: string, cantidad: ?float}>
   */
  private static function normalizarPiezas(array $piezas): array
  {
    return array_map(fn(array $p) => [
      'descripcion' => trim(preg_replace('/\s+/u', ' ', (string) ($p['descripcion'] ?? '')) ?? ''),
      'cantidad' => Validator::cantidad((string) ($p['cantidad'] ?? '1')),
    ], array_values($piezas));
  }

  /**
   * @param array<int, mixed> $items
   * @return array<int, array{cantidad: ?float, precio: ?float, modo: string}>
   */
  private static function normalizarItems(array $items): array
  {
    if (array_is_list($items) && ($items === [] || !is_array($items[0]))) {
      $items = array_fill_keys(array_map('intval', $items), []);
    }

    $normalizados = [];
    foreach ($items as $id => $datos) {
      $cantidad = Validator::cantidad((string) ($datos['cantidad'] ?? '1'));
      $precioTexto = trim((string) ($datos['precio'] ?? ''));
      $normalizados[(int) $id] = [
        'cantidad' => $cantidad,
        'precio' => $precioTexto === '' ? null : (Validator::importe($precioTexto) ?? -1.0),
        'modo' => (string) ($datos['modo'] ?? '') ?: 'negocio',
      ];
    }

    return $normalizados;
  }

  /**
   * ¿El presupuesto sigue vigente? Días de validez configurados contados desde el último
   * envío al cliente (o desde que se creó la orden, si nunca se envió).
   */
  public function presupuestoVigente(array $orden): bool
  {
    $validez = (int) $this->configuracion->seccion('trabajo')['validez'];
    $desde = substr((string) ($orden['presupuesto_enviado'] ?? null ?: $orden['created_at']), 0, 10);

    return date('Y-m-d', strtotime("{$desde} +{$validez} days")) >= date('Y-m-d');
  }

  /**
   * ¿El cliente puede responder el presupuesto desde el link? También con la orden en
   * proceso: si se modifica un presupuesto ya aceptado (y el trabajo arrancó al aceptarlo),
   * la respuesta se anula y el cliente tiene que poder aceptar el nuevo.
   */
  public function admiteRespuestaPresupuesto(array $orden): bool
  {
    return self::editable(EstadoOrden::from($orden['estado']))
      && $orden['presupuesto_respuesta'] !== 'aceptado'
      && $this->presupuestoVigente($orden);
  }

  /**
   * Respuesta del cliente desde el link del presupuesto.
   *
   * @return array<string, mixed> la orden actualizada
   */
  public function responderPresupuesto(string $token, string $accion): array
  {
    $id = $this->ordenes->idPorToken($token) ?? throw new NotFoundException('El link no es válido, venció o la orden ya no existe.');
    $orden = $this->obtener($id);

    (new Validator())
      ->check(in_array($accion, ['aceptar', 'rechazar'], true), 'Acción inválida.')
      ->check($this->admiteRespuestaPresupuesto($orden), match (true) {
        $orden['presupuesto_respuesta'] === 'aceptado' => 'Este presupuesto ya fue aceptado.',
        !self::editable(EstadoOrden::from($orden['estado'])) => 'El trabajo ya está finalizado o cancelado; comunicate con nosotros.',
        default => 'El presupuesto venció; comunicate con nosotros para actualizarlo.',
      })
      ->validate();

    $respuesta = $accion === 'aceptar' ? 'aceptado' : 'rechazado';
    $this->ordenes->registrarRespuestaPresupuesto($id, $respuesta);
    $this->auditor->registrar("presupuesto_{$respuesta}", 'orden', $id, "El cliente {$respuesta} el presupuesto de la orden #{$id}", actor: 'Cliente (link)');

    if ($respuesta === 'aceptado' && $this->configuracion->seccion('trabajo')['aceptar_inicia_trabajo']) {
      $this->cambiarEstado($id, EstadoOrden::EnProceso->value);
    }

    return $this->obtener($id);
  }

  /**
   * Cambia el estado y mueve el stock de repuestos al entrar o salir de "finalizado".
   *
   * Una orden puede abrirse sin ítems (el equipo entra a diagnóstico), pero no terminarse:
   * lo que se entrega y se cobra tiene que figurar en el detalle.
   */
  public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null): EstadoOrden
  {
    $this->obtener($id);

    $nuevo = EstadoOrden::tryFrom($estado);
    (new Validator())->check($nuevo !== null, 'Estado de orden inválido.')->validate();
    (new Validator())
      ->check($nuevo !== EstadoOrden::Finalizado || $this->ordenes->items($id) !== [], 'Para terminar el trabajo, primero cargá en la orden los servicios o repuestos que se hicieron.')
      ->validate();

    // La orden se lee bloqueada dentro de la transacción: dos cambios simultáneos (dos
    // pestañas, o el cliente aceptando mientras un empleado finaliza) se ejecutan uno
    // detrás del otro y el stock no se descuenta dos veces.
    $orden = $this->ordenes->transaction(function () use ($id, $nuevo, $usuarioId) {
      $orden = $this->ordenes->bloquear($id) ?? throw new NotFoundException('Orden no encontrada.');
      $descontado = (bool) $orden['stock_descontado'];
      // Un equipo entregado no vuelve al local por un cambio de estado (p. ej. desde una pestaña
      // vieja): primero se deshace la entrega, que borra también la firma de entrega.
      (new Validator())
        ->check($orden['entregado_en'] === null || $nuevo === EstadoOrden::Finalizado,
          'Este equipo ya se entregó. Para volver a abrir el trabajo, primero tocá «Deshacer la entrega» en la orden.')
        ->validate();

      if ($nuevo === EstadoOrden::Finalizado && !$descontado) {
        $this->stock->descontarOrden($id, $usuarioId);
      } elseif ($nuevo !== EstadoOrden::Finalizado && $descontado) {
        $this->stock->reponerOrden($id, $usuarioId);
      }

      $this->ordenes->setEstado($id, $nuevo);

      return $orden;
    });

    if ($orden['estado'] !== $nuevo->value) {
      $this->auditor->registrar('cambiar_estado', 'orden', $id, "Orden #{$id}: " . EstadoOrden::from($orden['estado'])->label() . " → {$nuevo->label()}");
    }

    return $nuevo;
  }
}
