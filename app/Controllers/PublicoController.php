<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\TurnoRepository;
use App\Services\ConfiguracionService;
use App\Services\PortalService;
use App\Services\TurnoService;

/**
 * Páginas públicas (sin login): confirmación de turnos por link y portal
 * "Seguí tu equipo".
 */
final class PublicoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly TurnoRepository $turnos,
    private readonly TurnoService $turnoService,
    private readonly PortalService $portal,
    private readonly ConfiguracionService $configuracion,
    private readonly \App\Repositories\OrdenRepository $ordenes,
    private readonly \App\Services\OrdenService $ordenService,
    private readonly \App\Services\DocumentoService $documentos,
    private readonly \App\Services\NotificacionService $notificaciones,
  ) {
    parent::__construct($view, $session);
  }

  /** Página del link enviado por email. Solo muestra: confirmar/cancelar se hace por POST. */
  public function turno(Request $request, string $token): void
  {
    $turno = $this->turnos->porToken($token) ?? throw new NotFoundException('El link no es válido, venció o el turno ya no existe.');

    $this->publico('publico/turno', [
      'title' => 'Tu turno',
      'turno' => $turno,
      'token' => $token,
      'admiteRespuesta' => TurnoService::admiteRespuesta($turno),
    ]);
  }

  public function confirmarTurno(Request $request, string $token): void
  {
    $this->responder($request, $token, 'confirmar', '¡Gracias! Tu turno quedó confirmado.');
  }

  public function cancelarTurno(Request $request, string $token): void
  {
    $this->responder($request, $token, 'cancelar', 'Tu turno fue cancelado. Si querés reprogramarlo, comunicate con nosotros.');
  }

  /** Presupuesto enviado por email: el cliente lo ve y lo acepta o rechaza. */
  public function presupuesto(Request $request, string $token): void
  {
    $id = $this->ordenes->idPorToken($token) ?? throw new NotFoundException('El link no es válido, venció o la orden ya no existe.');
    $datos = $this->documentos->datos($id);

    $this->publico('publico/presupuesto', [
      ...$datos,
      'title' => "Presupuesto N° {$datos['numero']}",
      'token' => $token,
      'admiteRespuesta' => $this->ordenService->admiteRespuestaPresupuesto($datos['orden']),
      'vigente' => $this->ordenService->presupuestoVigente($datos['orden']),
      'portal' => (bool) $this->portal->opciones()['habilitado'],
    ]);
  }

  public function presupuestoPdf(Request $request, string $token): void
  {
    $id = $this->ordenes->idPorToken($token) ?? throw new NotFoundException('El link no es válido, venció o la orden ya no existe.');
    $pdf = $this->documentos->pdf($id);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdf['nombre'] . '"');
    header('Cache-Control: no-store, private');
    echo $pdf['contenido'];
  }

  public function responderPresupuesto(Request $request, string $token): void
  {
    $this->verifyCsrf($request);
    $accion = $request->string('accion');

    try {
      $orden = $this->ordenService->responderPresupuesto($token, $accion);
      $this->success($accion === 'aceptar'
        ? '¡Gracias! Aceptaste el presupuesto. Te avisamos cuando el equipo esté listo.'
        : 'Registramos que no aceptás el presupuesto. Si querés revisarlo, comunicate con nosotros.');

      $numero = str_pad((string) $orden['id'], 4, '0', STR_PAD_LEFT);
      $this->notificaciones->avisarNegocio(
        "Presupuesto N° {$numero} " . ($accion === 'aceptar' ? 'ACEPTADO' : 'rechazado') . " por el cliente",
        "El cliente " . ($accion === 'aceptar' ? 'aceptó' : 'rechazó') . " el presupuesto N° {$numero} (" . equipo_texto($orden) . ").

"
          . 'Ver la orden: ' . absolute_url("ordenes/{$orden['id']}"),
      );
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/presupuesto/{$token}");
  }

  /** Estado del trabajo desde el link del presupuesto, sin pedir DNI ni número de orden. */
  public function seguimientoPresupuesto(Request $request, string $token): void
  {
    $id = $this->ordenes->idPorToken($token) ?? throw new NotFoundException('El link no es válido, venció o la orden ya no existe.');

    $this->publico('publico/seguimiento', [
      'title' => 'Seguí tu equipo',
      'opciones' => $this->portal->opciones(),
      'resultado' => $this->portal->consultarPorOrden($id),
      'volver' => url("presupuesto/{$token}"),
    ]);
  }

  public function seguimiento(Request $request): void
  {
    $opciones = $this->portal->opciones();
    if (!$opciones['habilitado']) {
      throw new NotFoundException('La consulta en línea no está disponible.');
    }

    $this->publico('publico/seguimiento', ['title' => 'Seguí tu equipo', 'opciones' => $opciones, 'resultado' => null]);
  }

  public function consultar(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $resultado = $this->portal->consultar($request->string('dni'), $request->string('orden'), Request::ip());
    } catch (ValidationException $e) {
      $this->session->keepInput(['dni' => $request->string('dni'), 'orden' => $request->string('orden')]);
      $this->error($e->getMessage());
      $this->redirect('/seguimiento');
    }

    // El resultado se muestra en la respuesta del POST: los datos no quedan en la URL ni en el historial.
    $this->publico('publico/seguimiento', ['title' => 'Seguí tu equipo', 'opciones' => $this->portal->opciones(), 'resultado' => $resultado]);
  }

  private function responder(Request $request, string $token, string $accion, string $exito): void
  {
    $this->verifyCsrf($request);

    try {
      $this->turnoService->responderCliente($token, $accion);
      $this->success($exito);
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect("/turno/{$token}");
  }

  /**
   * Las páginas públicas muestran datos personales: no se guardan en caché (ni del
   * navegador ni de proxies). La dirección con el token tampoco se pasa a otros sitios
   * (Referrer-Policy same-origin, en public/.htaccess).
   *
   * @param array<string, mixed> $datos
   */
  private function publico(string $vista, array $datos): void
  {
    header('Cache-Control: no-store, private');
    echo $this->view->render($vista, [...$datos, 'negocio' => $this->configuracion->seccion('negocio')], 'layouts/publico');
  }
}
