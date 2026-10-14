<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\EstadoOrden;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\EmpleadoRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Repositories\EquipoRepository;
use App\Services\ConfiguracionService;
use App\Services\OrdenService;
use App\Services\DocumentoService;

final class OrdenController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly OrdenService $service,
    private readonly OrdenRepository $ordenes,
    private readonly EquipoRepository $equipos,
    private readonly ServicioRepository $servicios,
    private readonly RepuestoRepository $repuestos,
    private readonly DocumentoService $documentos,
    private readonly Auth $auth,
    private readonly PagoRepository $pagos,
    private readonly ClienteRepository $clientes,
    private readonly ConfiguracionService $configuracion,
    private readonly EmpleadoRepository $empleados,
    private readonly \App\Repositories\AuditoriaRepository $auditoria,
    private readonly \App\Repositories\TurnoRepository $turnos,
    private readonly \App\Services\NotificacionService $notificaciones,
    private readonly \App\Repositories\ComboRepository $combos,
    private readonly \App\Services\FirmaService $firmas,
    private readonly \App\Services\Auditor $auditor,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('ordenes/index', ['title' => 'Órdenes de servicio', 'estados' => EstadoOrden::cases()]);
  }

  /** Tablero: los equipos del local en columnas (Recibido · En reparación · Listo), para arrastrar de una a otra. */
  public function tablero(Request $request): void
  {
    $columnas = array_fill_keys([EstadoOrden::Pendiente->value, EstadoOrden::EnProceso->value, EstadoOrden::Finalizado->value], []);
    foreach ($this->ordenes->tablero() as $orden) {
      $columnas[$orden['estado']][] = $orden;
    }

    $this->render('ordenes/tablero', ['title' => 'Tablero del local', 'columnas' => $columnas]);
  }

  public function datos(Request $request): void
  {
    $this->tabla($this->ordenes->paginar($request->queryAll()), 'ordenes/_fila', 'o', ['estados' => EstadoOrden::cases()]);
  }

  public function create(Request $request): void
  {
    // Desde un turno: equipo y falla precargados.
    $turno = ($turnoId = $request->int('turno_id')) ? $this->turnos->find($turnoId) : null;
    $orden = $turno ? ['turno_id' => (int) $turno['id'], 'falla_reportada' => $turno['descripcion']] : null;

    $this->form('Nueva orden de reparación', $orden, ['servicio' => [], 'repuesto' => [], 'pieza' => []], $turno ? (int) $turno['equipo_id'] : (int) $request->int('equipo_id'), true);
  }

  public function store(Request $request): void
  {
    $this->save($request, null);
  }

  public function edit(Request $request, int $id): void
  {
    $orden = $this->service->obtener($id);

    if (!OrdenService::editable(EstadoOrden::from($orden['estado']))) {
      $this->error('Solo se pueden editar órdenes recibidas o en reparación.');
      $this->redirect('/ordenes');
    }

    $detalle = ['servicio' => [], 'repuesto' => [], 'pieza' => []];
    foreach ($this->ordenes->items($id) as $item) {
      if ($item['descripcion'] !== null) {
        $detalle['pieza'][] = ['descripcion' => $item['descripcion'], 'cantidad' => (float) $item['cantidad']];
        continue;
      }
      $tipo = $item['repuesto_id'] !== null ? 'repuesto' : 'servicio';
      $detalle[$tipo][(int) $item["{$tipo}_id"]] = [
        'cantidad' => (float) $item['cantidad'],
        'precio' => (float) $item['precio_unitario'],
        'modo' => OrdenService::modo($item),
      ];
    }

    $this->form("Editar orden #{$id}", $orden, $detalle);
  }

  public function update(Request $request, int $id): void
  {
    $this->save($request, $id);
  }

  /** Ficha de la orden: detalle, pagos y accesos a presupuesto y comprobante. */
  public function show(Request $request, int $id): void
  {
    $orden = $this->service->obtener($id);
    $pagos = $this->pagos->porOrden($id);

    $cliente = $this->clientes->find((int) $orden['cliente_id']);

    $this->render('ordenes/show', [
      'title' => "Orden #{$id}",
      'orden' => $orden,
      'cliente' => $cliente,
      'turno' => $orden['turno_id'] ? $this->turnos->find((int) $orden['turno_id']) : null,
      'items' => $this->ordenes->items($id),
      // Para que el botón "Terminar el trabajo" diga qué va a pasar.
      'avisaListo' => $this->notificaciones->avisoListoActivo() && !$this->notificaciones->equipoListoAvisado($id) && !empty($cliente['email']),
      'pagos' => $pagos,
      'saldo' => round((float) $orden['total'] - array_sum(array_column($pagos, 'monto')), 2),
      'formasPago' => $this->configuracion->obtener()['trabajo']['forma_pago'],
      'estado' => EstadoOrden::from($orden['estado']),
      'historial' => $this->auditoria->deEntidad('orden', $id),
      'puedeEnviar' => $this->notificaciones->hayCanalDisponible(),
      'whatsapp' => $this->notificaciones->botonWhatsappManual() && strlen(preg_replace('/\D/', '', (string) $cliente['telefono'])) >= 10,
      'fotos' => $this->equipos->imagenesDeOrden($id),
      'firmas' => $this->firmas->deOrden($id),
      'puedeDeshacerEntrega' => $orden['entregado_en'] !== null && ($this->auth->esAdministrador()
        || strtotime((string) $orden['entregado_en']) >= time() - \App\Services\EntregaService::MINUTOS_PARA_DESHACER * 60),
    ]);
  }

  public function enviarPresupuesto(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $canal = $this->notificaciones->enviarPresupuesto($id);
      $canal !== null
        ? $this->success("Presupuesto enviado al cliente por {$canal}.")
        : $this->error('El cliente no tiene email cargado.');
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/ordenes/{$id}");
  }

  /**
   * Botones de WhatsApp de la orden: abren la conversación con el mensaje y el link armados
   * (se abren en otra pestaña; si algo falta, esa pestaña muestra la orden con el motivo).
   */
  public function whatsapp(Request $request, int $id, string $tipo): void
  {
    $this->verifyCsrf($request);

    try {
      $url = $this->notificaciones->whatsappOrden($id, $tipo);
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
      $this->redirect("/ordenes/{$id}");
    }

    $nombre = \App\Services\NotificacionService::NOMBRES[$tipo] ?? $tipo;
    $this->auditor->registrar('whatsapp', 'orden', $id, "Orden #{$id}: se abrió WhatsApp para mandarle al cliente: " . mb_strtolower($nombre));
    // La pantalla de la orden se recarga sola (app.js) y muestra esto.
    $this->success("Se abrió WhatsApp con el mensaje ({$nombre}). Tocá «Enviar» allá para que le llegue al cliente." . self::siNoSeAbrio($this->configuracion->seccion('notificaciones')));
    $this->redirectExterno($url);
  }

  /** Con la app de escritorio, si no está instalada no pasa nada: se dice qué hacer. */
  public static function siNoSeAbrio(array $notificaciones): string
  {
    return whatsapp_modo($notificaciones) === 'app'
      ? ' Si no se abrió, instalá WhatsApp en esta computadora o elegí «WhatsApp Web» en Configuración › Avisos.'
      : '';
  }

  public function cambiarEstado(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $anterior = $this->service->obtener($id)['estado'];
    $estado = $this->service->cambiarEstado($id, $request->string('estado'), $this->auth->id());
    $mensaje = "Orden #{$id}: {$estado->label()}.";

    if ($estado === EstadoOrden::Finalizado && $anterior !== EstadoOrden::Finalizado->value) {
      $mensaje .= $this->avisarEquipoListo($id);
    }

    // La pantalla se recarga después del cambio: el mensaje se muestra como aviso. Desde los
    // botones de la ficha, lo que se revierte sin efectos afuera (no "terminar": avisa al
    // cliente y mueve stock) se hace sin preguntar y se ofrece "Deshacer".
    $revertible = !$request->isAjax() && $request->string('deshaciendo') !== '1'
      && $estado !== EstadoOrden::Finalizado && $anterior !== EstadoOrden::Finalizado->value && $anterior !== $estado->value;
    // Desde el tablero (menú "Mover a…") se vuelve al tablero; desde la ficha, a la orden.
    $volver = $request->string('volver') === 'tablero' ? 'tablero' : '';
    if ($request->isAjax()) {
      // Por AJAX el mensaje va en la respuesta. Se guarda para la próxima pantalla solo si la
      // página se va a recargar (recargar=1): si no, aparecía después, junto con los de otros cambios.
      if ($request->string('recargar') === '1') {
        $this->success($mensaje);
      }
      $this->json(['status' => 'success', 'message' => $mensaje]);
    }
    $revertible
      ? $this->hechoConDeshacer($mensaje, "ordenes/{$id}/estado", ['estado' => $anterior, 'volver' => $volver])
      : $this->success($mensaje);
    // Botones de la ficha ("Empezar el trabajo", "Terminar el trabajo"): vuelve a la orden.
    $this->redirect($volver === 'tablero' ? '/ordenes/tablero' : "/ordenes/{$id}");
  }

  /** Aviso "equipo listo" al finalizar la orden. Devuelve el texto a sumar al mensaje; si falla, deja un error. */
  private function avisarEquipoListo(int $id): string
  {
    if (!$this->notificaciones->avisoListoActivo() || $this->notificaciones->equipoListoAvisado($id)) {
      return '';
    }

    try {
      $canal = $this->notificaciones->avisarEquipoListo($id);

      return $canal !== null ? " Le avisamos al cliente por {$canal} que el equipo está listo." : ' El cliente no tiene email para avisarle que está listo.';
    } catch (\RuntimeException $e) {
      $this->aviso("El trabajo quedó terminado, pero no se pudo avisar al cliente que el equipo está listo. {$e->getMessage()}");

      return '';
    }
  }

  public function presupuesto(Request $request, int $id): void
  {
    $this->documento($request, $id, false);
  }

  public function entrega(Request $request, int $id): void
  {
    $this->documento($request, $id, true);
  }

  /** Ticket para impresora térmica, con QR (ver DocumentoService::ticket). */
  public function ticket(Request $request, int $id, string $tipo): void
  {
    try {
      $datos = $this->documentos->ticket($id, $tipo);
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
      $this->redirect("/ordenes/{$id}");
    }

    echo $this->view->render('tickets/show', [...$datos, 'imprimir' => $request->query('imprimir') === '1'], null);
  }

  public function pdf(Request $request, int $id): void
  {
    $this->descargar($id, false);
  }

  public function entregaPdf(Request $request, int $id): void
  {
    $this->descargar($id, true);
  }

  private function documento(Request $request, int $id, bool $entrega): void
  {
    try {
      $datos = $this->documentos->datos($id, $entrega);
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
      $this->redirect("/ordenes/{$id}");
    }

    echo $this->view->render('presupuestos/show', [...$datos, 'imprimir' => $request->query('imprimir') === '1'], null);
  }

  private function descargar(int $id, bool $entrega): void
  {
    try {
      $pdf = $this->documentos->pdf($id, $entrega);
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
      $this->redirect("/ordenes/{$id}");
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdf['nombre'] . '"');
    header('Content-Length: ' . strlen($pdf['contenido']));
    echo $pdf['contenido'];
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);
    $estabaAceptado = $id !== null && $this->service->obtener($id)['presupuesto_respuesta'] === 'aceptado';

    try {
      $nuevoId = $this->service->guardar(
        (int) $request->int('equipo_id'),
        $this->items($request, 'servicio'),
        $this->items($request, 'repuesto'),
        $id,
        $request->int('tecnico_id') ?: null,
        $request->all(),
        self::piezasCliente((array) $request->input('pieza_descripcion', []), (array) $request->input('pieza_cantidad', [])),
      );
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/ordenes/{$id}/editar" : '/ordenes/crear', $e, $request);
    }

    $this->success($id ? 'Orden actualizada correctamente.' : 'Orden creada correctamente.');

    // Cambiaron los ítems o los precios de un presupuesto aceptado: la aceptación quedó sin
    // efecto y el cliente tiene que volver a aprobarlo.
    if ($estabaAceptado && $this->service->obtener($nuevoId)['presupuesto_respuesta'] === null) {
      $this->avisarPresupuestoModificado($nuevoId);
    }

    $this->redirect('/ordenes/' . ($id ?? $nuevoId));
  }

  private function avisarPresupuestoModificado(int $id): void
  {
    $pendiente = 'El cliente tiene que volver a aceptar el presupuesto modificado';

    if (!$this->notificaciones->hayCanalDisponible()) {
      $this->aviso("{$pendiente}: no hay canal de envío configurado, avisale por otro medio.");

      return;
    }

    try {
      $canal = $this->notificaciones->enviarPresupuestoModificado($id);
      $canal !== null
        ? $this->success("Orden actualizada. Como el cliente ya había aceptado el presupuesto, le avisamos del cambio por {$canal} para que lo vuelva a aceptar.")
        : $this->aviso("{$pendiente}, pero no tiene email cargado: avisale por otro medio.");
    } catch (\RuntimeException $e) {
      // También una ValidationException: p. ej., se quitaron todos los ítems y no hay presupuesto que mandar.
      $this->aviso("{$pendiente}. {$e->getMessage()}");
    }
  }

  /**
   * Arma el mapa id => [cantidad, precio, modo] a partir de los campos del formulario
   * (servicio_id[], cantidad_servicio[id], precio_servicio[id], modo_repuesto[id]).
   *
   * @return array<int, array{cantidad: string, precio: string, modo?: string}>
   */
  private function items(Request $request, string $tipo): array
  {
    $cantidades = (array) $request->input("cantidad_{$tipo}", []);
    $precios = (array) $request->input("precio_{$tipo}", []);
    $modos = (array) $request->input("modo_{$tipo}", []);

    $items = [];
    foreach ($request->intList("{$tipo}_id") as $id) {
      $items[$id] = [
        'cantidad' => (string) ($cantidades[$id] ?? '1'),
        'precio' => (string) ($precios[$id] ?? ''),
      ];
      if ($tipo === 'repuesto') {
        $items[$id]['modo'] = (string) ($modos[$id] ?? 'negocio');
      }
    }

    return $items;
  }

  /**
   * Piezas que trae el cliente y no están en el catálogo (pieza_descripcion[], pieza_cantidad[]).
   * Las filas que quedaron en blanco se ignoran.
   *
   * @param array<mixed> $descripciones
   * @param array<mixed> $cantidades
   * @return list<array{descripcion: string, cantidad: string}>
   */
  private static function piezasCliente(array $descripciones, array $cantidades): array
  {
    $piezas = [];
    foreach (array_values($descripciones) as $i => $descripcion) {
      $cantidad = (string) (array_values($cantidades)[$i] ?? '1');
      if (trim((string) $descripcion) === '' && in_array(trim($cantidad), ['', '1'], true)) {
        continue;
      }
      $piezas[] = ['descripcion' => (string) $descripcion, 'cantidad' => $cantidad];
    }

    return $piezas;
  }

  /**
   * @param array<string, mixed>|null $orden
   * @param array{servicio: array<int, array<string, mixed>>, repuesto: array<int, array<string, mixed>>, pieza: list<array<string, mixed>>} $detalle
   */
  private function form(string $title, ?array $orden, array $detalle, int $equipoSugerido = 0, bool $nueva = false): void
  {
    $precarga = $nueva ? ($orden ?? []) : [];
    $orden = $nueva ? null : $orden;

    $equipos = $this->equipos->activos();

    // Al editar, el equipo de la orden debe figurar aunque hoy esté inactivo.
    if ($orden && !in_array((int) $orden['equipo_id'], array_map('intval', array_column($equipos, 'id')), true)) {
      $equipos[] = $this->equipos->find((int) $orden['equipo_id']);
    }

    // Si se vuelve con errores, se respeta lo que se había cargado.
    if ($this->session->hasOldInput()) {
      foreach (['servicio', 'repuesto'] as $tipo) {
        $detalle[$tipo] = [];
        $cantidades = (array) old("cantidad_{$tipo}", []);
        $precios = (array) old("precio_{$tipo}", []);
        $modos = (array) old("modo_{$tipo}", []);
        foreach ((array) old("{$tipo}_id", []) as $itemId) {
          $detalle[$tipo][(int) $itemId] = ['cantidad' => $cantidades[$itemId] ?? 1, 'precio' => $precios[$itemId] ?? '', 'modo' => $modos[$itemId] ?? 'negocio'];
        }
      }
      $detalle['pieza'] = self::piezasCliente((array) old('pieza_descripcion', []), (array) old('pieza_cantidad', []));
    }

    $this->render('ordenes/form', [
      'title' => $title,
      'orden' => $orden,
      'equipos' => $equipos,
      'servicios' => $this->servicios->all(),
      'repuestos' => $this->repuestos->all(),
      'detalle' => $detalle,
      'equipoSugerido' => $equipoSugerido,
      'tecnicos' => $this->empleados->activos(),
      'precarga' => $precarga,
      'combos' => $this->combos->all(true),
      'mantenimiento' => $this->configuracion->seccion('mantenimiento'),
    ]);
  }
}
