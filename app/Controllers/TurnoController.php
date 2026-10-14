<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\EstadoTurno;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\EquipoRepository;
use App\Services\ConfiguracionService;
use App\Services\NotificacionService;
use App\Services\TurnoService;

final class TurnoController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly TurnoService $service,
    private readonly TurnoRepository $turnos,
    private readonly ClienteRepository $clientes,
    private readonly EquipoRepository $equipos,
    private readonly NotificacionService $notificaciones,
    private readonly ConfiguracionService $configuracion,
    private readonly \App\Services\AgendaService $agenda,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('turnos/index', ['title' => 'Agenda de turnos', 'estados' => EstadoTurno::cases()]);
  }

  public function datos(Request $request): void
  {
    $this->tabla($this->turnos->paginar($request->queryAll()), 'turnos/_fila', 't', [
      'estados' => EstadoTurno::cases(),
      'avisos' => $this->avisos(),
    ]);
  }

  public function create(Request $request): void
  {
    // Desde la agenda semanal llegan fecha y hora; desde la ficha, el cliente.
    $sugerido = array_filter([
      'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('fecha')) ? $request->query('fecha') : null,
      'hora' => preg_match('/^\d{2}:\d{2}$/', (string) $request->query('hora')) ? $request->query('hora') : null,
    ]);
    $this->form('Nuevo turno', null, (int) $request->int('cliente_id'), $sugerido);
  }

  public function semana(Request $request): void
  {
    $this->render('turnos/semana', [
      'title' => 'Agenda semanal',
      'agenda' => $this->agenda->semana((string) $request->query('desde', ''), new \DateTimeImmutable()),
    ]);
  }

  /** JSON: horarios del día con sus cupos libres, para elegir tocando en vez de escribir la hora. */
  public function horarios(Request $request): void
  {
    $this->json($this->agenda->dia((string) $request->query('fecha', ''), new \DateTimeImmutable()));
  }

  public function store(Request $request): void
  {
    $this->save($request, null);
  }

  public function edit(Request $request, int $id): void
  {
    $this->form("Editar turno #{$id}", $this->service->obtener($id));
  }

  public function update(Request $request, int $id): void
  {
    $this->save($request, $id);
  }

  public function cambiarEstado(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $estado = $this->service->cambiarEstado($id, $request->string('estado'));
    $this->json(['status' => 'success', 'message' => "Turno #{$id}: {$estado->label()}."]);
  }

  /** Botón manual: registra el aviso y abre WhatsApp con el mensaje armado. */
  public function whatsapp(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $url = $this->notificaciones->whatsappManual($id, $request->string('tipo'));
    // La pantalla de turnos se recarga sola (app.js) y muestra esto.
    $this->success('Se abrió WhatsApp con el mensaje del turno. Tocá «Enviar» allá para que le llegue al cliente.'
      . OrdenController::siNoSeAbrio($this->configuracion->seccion('notificaciones')));
    $this->redirectExterno($url);
  }

  /** Botón manual: envía ahora el recordatorio por el canal disponible (email). */
  public function recordar(Request $request, int $id): void
  {
    $this->verifyCsrf($request);
    $this->avisar(fn() => $this->notificaciones->enviarRecordatorio($id), 'Recordatorio enviado');

    $this->redirect($request->string('volver') === 'inicio' ? '/' : '/turnos');
  }

  /** Reenvía el pedido de confirmación al cliente. */
  public function pedirConfirmacion(Request $request, int $id): void
  {
    $this->verifyCsrf($request);
    $this->avisar(fn() => $this->notificaciones->enviarConfirmacion($id), 'Pedido de confirmación enviado');

    $this->redirect('/turnos');
  }

  /** Ejecuta un envío y deja el resultado como mensaje flash. */
  private function avisar(callable $envio, string $exito): void
  {
    try {
      $canal = $envio();
      $canal !== null
        ? $this->success("{$exito} por {$canal}.")
        : $this->error('El cliente no tiene datos de contacto para los canales configurados (por ejemplo, email).');
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
    }
  }

  public function destroy(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $this->service->eliminar($id);
    $this->success('Turno eliminado.');
    $this->redirect('/turnos');
  }

  private function save(Request $request, ?int $id): void
  {
    $this->verifyCsrf($request);
    $anterior = $id ? $this->service->obtener($id) : null;

    try {
      $turnoId = $this->service->guardar($request->all(), $id);
    } catch (ValidationException $e) {
      $this->backWithErrors($id ? "/turnos/{$id}/editar" : '/turnos/crear', $e, $request);
    }

    $guardado = $id ? 'Turno actualizado correctamente.' : 'Turno agendado correctamente.';
    $avisado = false; // si sale un aviso amarillo, ya dice que el turno quedó guardado: no va además el verde

    // Turno nuevo o reprogramado: se pide (de nuevo) la confirmación del cliente.
    $turno = $this->turnos->detalle($turnoId);
    $reprogramado = $anterior && ($anterior['fecha'] !== $turno['fecha'] || substr($anterior['hora'], 0, 5) !== substr($turno['hora'], 0, 5));
    if ($reprogramado) {
      $this->turnos->reiniciarConfirmacion($turnoId);
    }
    if ((!$anterior || $reprogramado) && TurnoService::admiteRespuesta($turno) && $this->configuracion->seccion('turnos')['enviar_confirmacion']) {
      // El turno ya quedó guardado: si no se pudo avisar, es un aviso (amarillo), no un error.
      // Con un error en rojo la gente creía que el turno no se había guardado y lo cargaba de nuevo.
      try {
        $canal = $this->notificaciones->enviarConfirmacion($turnoId);
        $canal !== null
          ? $this->success("Se envió al cliente el pedido de confirmación por {$canal}.")
          : $this->aviso('El turno quedó guardado, pero no le pudimos pedir la confirmación porque el cliente no tiene email. Avisale por WhatsApp (botón verde en el listado de turnos) o por teléfono.');
        $avisado = $canal === null;
      } catch (\RuntimeException $e) {
        $this->aviso("El turno quedó guardado, pero no se pudo enviar el pedido de confirmación: {$e->getMessage()} Avisale al cliente por otro medio.");
        $avisado = true;
      }
    }
    if (!$avisado) {
      $this->success($guardado);
    }

    $this->redirect('/turnos');
  }

  /** @return array{canal: bool, whatsapp: bool} qué botones de aviso mostrar */
  private function avisos(): array
  {
    return [
      'canal' => $this->notificaciones->hayCanalDisponible(),
      'whatsapp' => $this->notificaciones->botonWhatsappManual(),
    ];
  }

  /** @param array<string, mixed>|null $turno */
  private function form(string $title, ?array $turno, int $clienteSugerido = 0, array $sugerido = []): void
  {
    $clienteId = (int) old('cliente_id', $turno['cliente_id'] ?? $clienteSugerido);

    $this->render('turnos/form', [
      'title' => $title,
      'turno' => $turno,
      'clientes' => $this->clientes->activos(),
      'equipos' => $clienteId > 0 ? $this->equipos->activosPorCliente($clienteId) : [],
      'estados' => EstadoTurno::cases(),
      'clienteSugerido' => $clienteSugerido,
      'horario' => $this->configuracion->horario(),
      'sugerido' => $sugerido,
    ]);
  }
}
