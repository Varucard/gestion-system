<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoTurno;
use App\Exceptions\NotFoundException;
use App\Models\Turno;
use App\Repositories\ClienteRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\EquipoRepository;
use App\Support\Validator;

final class TurnoService
{
  public function __construct(
    private readonly TurnoRepository $turnos,
    private readonly ClienteRepository $clientes,
    private readonly EquipoRepository $equipos,
    private readonly ConfiguracionService $configuracion,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->turnos->find($id) ?? throw new NotFoundException('Turno no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function guardar(array $input, ?int $id = null): int
  {
    if ($id !== null) {
      $this->obtener($id);
    }

    $clienteId = (int) ($input['cliente_id'] ?? 0);
    $equipoId = (int) ($input['equipo_id'] ?? 0);
    $fecha = trim((string) ($input['fecha'] ?? ''));
    $hora = substr(trim((string) ($input['hora'] ?? '')), 0, 5);
    $descripcion = Validator::nullable((string) ($input['descripcion'] ?? ''));
    $estado = EstadoTurno::tryFrom((string) ($input['estado'] ?? EstadoTurno::Pendiente->value));

    $v = (new Validator())
      ->check($clienteId > 0 && $this->clientes->find($clienteId) !== null, 'Seleccioná un cliente válido.')
      ->check($equipoId > 0 && $this->equipos->perteneceACliente($equipoId, $clienteId), 'El equipo no pertenece al cliente seleccionado.')
      ->check(Validator::fecha($fecha), 'La fecha no es válida.')
      ->check(Validator::hora($hora), 'La hora no es válida.')
      ->check($estado !== null, 'El estado del turno no es válido.')
      ->check($id !== null || !Validator::fecha($fecha) || $fecha >= date('Y-m-d'), 'No se pueden agendar turnos en fechas pasadas.');
    $v->validate();

    // El control de cupo y el guardado van bajo un mismo candado por fecha y hora: dos altas
    // simultáneas no pueden ocupar el último cupo a la vez.
    $guardado = $this->turnos->conCandado("turno {$fecha} {$hora}", function () use ($clienteId, $equipoId, $fecha, $hora, $descripcion, $estado, $id) {
      $this->validarHorario($fecha, $hora, $estado, $id);

      return $this->turnos->save(new Turno($clienteId, $equipoId, $fecha, $hora, $descripcion, $estado, $id));
    });
    $this->auditor->registrar($id === null ? 'crear' : 'editar', 'turno', $guardado, ($id === null ? 'Turno agendado' : 'Turno editado') . ' para el ' . format_date($fecha) . " {$hora}");

    return $guardado;
  }

  public function cambiarEstado(int $id, string $estado): EstadoTurno
  {
    $turno = $this->obtener($id);

    $nuevo = EstadoTurno::tryFrom($estado);
    $v = (new Validator())->check($nuevo !== null, 'Estado de turno inválido.');
    $v->validate();

    $hora = substr($turno['hora'], 0, 5);
    $this->turnos->conCandado("turno {$turno['fecha']} {$hora}", function () use ($id, $turno, $hora, $nuevo, $v) {
      if (!$nuevo->liberaHorario() && EstadoTurno::from($turno['estado'])->liberaHorario()) {
        $v->check($this->hayCupo($turno['fecha'], $hora, $id), 'No se puede reactivar: el horario ya está completo.')->validate();
      }

      $this->turnos->setEstado($id, $nuevo);
    });
    $this->auditor->registrar('cambiar_estado', 'turno', $id, "Turno #{$id}: " . EstadoTurno::from($turno['estado'])->label() . " → {$nuevo->label()}");

    return $nuevo;
  }

  /**
   * Respuesta del cliente desde el link del email (confirmar o cancelar).
   *
   * @return array<string, mixed> el turno actualizado
   */
  public function responderCliente(string $token, string $accion): array
  {
    $turno = $this->turnos->porToken($token) ?? throw new NotFoundException('El link no es válido, venció o el turno ya no existe.');
    $estado = EstadoTurno::from($turno['estado']);

    (new Validator())
      ->check(in_array($accion, ['confirmar', 'cancelar'], true), 'Acción inválida.')
      ->check(self::admiteRespuesta($turno), match (true) {
        $estado === EstadoTurno::Cancelado => 'Este turno ya fue cancelado.',
        $estado === EstadoTurno::Realizado, $estado === EstadoTurno::NoAsistio => 'Este turno ya pasó.',
        default => 'El horario del turno ya pasó; comunicate con nosotros.',
      })
      ->validate();

    $this->turnos->registrarRespuesta((int) $turno['id'], $accion === 'confirmar' ? EstadoTurno::Confirmado : EstadoTurno::Cancelado);
    $this->auditor->registrar($accion === 'confirmar' ? 'confirmar' : 'cancelar', 'turno', (int) $turno['id'], 'El cliente ' . ($accion === 'confirmar' ? 'confirmó' : 'canceló') . ' el turno del ' . format_date($turno['fecha']), actor: 'Cliente (link)');

    return $this->turnos->porToken($token);
  }

  /** ¿El cliente todavía puede confirmar o cancelar? (turno activo y futuro) */
  public static function admiteRespuesta(array $turno): bool
  {
    return in_array($turno['estado'], [EstadoTurno::Pendiente->value, EstadoTurno::Confirmado->value], true)
      && "{$turno['fecha']} " . substr($turno['hora'], 0, 5) > date('Y-m-d H:i');
  }

  /** Horario de atención, feriados y turnos simultáneos según la configuración. */
  private function validarHorario(string $fecha, string $hora, EstadoTurno $estado, ?int $id): void
  {
    if ($estado->liberaHorario()) {
      return;
    }

    $config = $this->configuracion->seccion('turnos');
    $horario = $this->configuracion->horario();
    $v = new Validator();

    if ($config['validar_horario']) {
      $franja = $horario->franja($fecha);
      $v->check(!$horario->esFeriado($fecha), 'Esa fecha es feriado.')
        ->check($horario->esFeriado($fecha) || $franja !== null, 'No atendemos ese día.')
        ->check($franja === null || $horario->dentroDeHorario($fecha, $hora), sprintf(
          'La hora está fuera del horario de atención%s.',
          $franja ? " ({$franja['desde']} a {$franja['hasta']})" : ''
        ));
    }

    $v->check($this->hayCupo($fecha, $hora, $id), $config['cupos_por_horario'] > 1
      ? 'Ese horario ya tiene todos los cupos ocupados.'
      : 'Ya hay un turno agendado para esa fecha y hora.')
      ->validate();
  }

  private function hayCupo(string $fecha, string $hora, ?int $exceptoId): bool
  {
    return $this->turnos->ocupados($fecha, $hora, $exceptoId) < $this->configuracion->seccion('turnos')['cupos_por_horario'];
  }

  public function eliminar(int $id): void
  {
    $turno = $this->obtener($id);
    $this->turnos->delete($id);
    $this->auditor->registrar('eliminar', 'turno', $id, 'Turno eliminado (era el ' . format_date($turno['fecha']) . ')');
  }
}
