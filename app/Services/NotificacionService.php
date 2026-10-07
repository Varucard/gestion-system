<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Enums\EstadoOrden;
use App\Exceptions\NotFoundException;
use App\Notificaciones\CanalNotificacion;
use App\Notificaciones\Destinatario;
use App\Notificaciones\EmailCanal;
use App\Notificaciones\Mensaje;
use App\Notificaciones\WhatsAppCanal;
use App\Repositories\NotificacionRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Support\Validator;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Avisos a clientes: confirmación y recordatorio de turnos, envío del
 * presupuesto (y de su modificación), equipo listo y aviso de próximo mantenimiento.
 *
 * El canal se elige según "notificaciones.canales" de la configuración: se usa
 * el primero que esté disponible y para el que el cliente tenga datos de
 * contacto. Los textos salen de las plantillas de "mensajes"
 * ({canal}_{tipo}_asunto y {canal}_{tipo}).
 */
final class NotificacionService
{
  public const CANALES = ['email', 'whatsapp'];
  public const CONFIRMACION = 'confirmacion';
  public const RECORDATORIO = 'recordatorio';
  public const PRESUPUESTO = 'presupuesto';
  public const PRESUPUESTO_MODIFICADO = 'presupuesto_modificado';
  public const LISTO = 'listo';
  public const MANTENIMIENTO = 'mantenimiento';

  /** Reintentos de los avisos automáticos si el envío falla. */
  private const MAX_ERRORES = 3;

  /** @var array<string, CanalNotificacion> canales reemplazados (tests, integraciones) */
  private array $canalesPropios = [];

  public function __construct(
    private readonly TurnoRepository $turnos,
    private readonly OrdenRepository $ordenes,
    private readonly \App\Repositories\PagoRepository $pagos,
    private readonly NotificacionRepository $registro,
    private readonly ConfiguracionService $configuracion,
    private readonly DocumentoService $documentos,
    private readonly Logger $logger,
  ) {
  }

  // ---------- Canales ----------

  /** Reemplaza la implementación de un canal (p. ej. un canal de prueba en los tests). */
  public function usarCanal(CanalNotificacion $canal): void
  {
    $this->canalesPropios[$canal->nombre()] = $canal;
  }

  public function canal(string $nombre): CanalNotificacion
  {
    return $this->canalesPropios[$nombre] ?? match ($nombre) {
      'email' => new EmailCanal($this->configuracion->seccion('negocio')),
      'whatsapp' => new WhatsAppCanal($this->configuracion->seccion('notificaciones')['codigo_pais']),
      default => throw new RuntimeException("Canal de notificación desconocido: {$nombre}"),
    };
  }

  /** @return list<CanalNotificacion> canales activos en la configuración y listos para enviar */
  public function canalesDisponibles(): array
  {
    $canales = array_map(fn(string $c) => $this->canal($c), $this->configuracion->seccion('notificaciones')['canales']);

    return array_values(array_filter($canales, fn(CanalNotificacion $c) => $c->disponible()));
  }

  public function hayCanalDisponible(): bool
  {
    return $this->canalesDisponibles() !== [];
  }

  public function botonWhatsappManual(): bool
  {
    return (bool) $this->configuracion->seccion('notificaciones')['boton_whatsapp_manual'];
  }

  // ---------- Turnos ----------

  /**
   * @return string|null canal usado, o null si el cliente no tiene contacto para ningún canal
   * @throws RuntimeException si el envío falla
   */
  public function enviarConfirmacion(int $turnoId): ?string
  {
    $canal = $this->enviarTurno(self::CONFIRMACION, $turnoId);
    if ($canal !== null) {
      $this->turnos->registrarConfirmacionEnviada($turnoId);
    }

    return $canal;
  }

  /** Recordatorio inmediato (botón manual). */
  public function enviarRecordatorio(int $turnoId): ?string
  {
    $canal = $this->enviarTurno(self::RECORDATORIO, $turnoId);
    if ($canal !== null) {
      $this->turnos->registrarRecordatorio($turnoId, $canal);
    }

    return $canal;
  }

  /**
   * Recordatorios automáticos: en días hábiles, dentro del horario de atención
   * y desde la hora configurada, para los turnos hasta el próximo día hábil.
   *
   * @return array{enviados: int, sin_contacto: int, errores: int, omitido: ?string}
   */
  public function enviarRecordatoriosPendientes(DateTimeImmutable $ahora): array
  {
    $config = $this->configuracion->seccion('turnos');
    $omitido = $this->motivoParaNoEnviar($ahora, (bool) $config['recordatorio_automatico'], 'Recordatorio automático desactivado.');
    if ($omitido !== null) {
      return ['enviados' => 0, 'sin_contacto' => 0, 'errores' => 0, 'omitido' => $omitido];
    }

    $hoy = $ahora->format('Y-m-d');
    $hasta = $this->configuracion->horario()->siguienteDiaHabil($hoy) ?? $hoy;
    $manana = $ahora->modify('+1 day')->format('Y-m-d');

    return $this->procesarLote(
      $this->turnos->pendientesDeRecordatorio($manana, $hasta),
      reservar: fn(int $id) => $this->turnos->reservarRecordatorio($id),
      enviar: fn(int $id) => $this->enviarTurno(self::RECORDATORIO, $id),
      registrar: fn(int $id, ?string $canal) => $this->turnos->registrarRecordatorio($id, $canal ?? 'sin_contacto'),
      liberar: fn(int $id) => $this->registro->erroresRecientes($id, self::RECORDATORIO) < self::MAX_ERRORES
        ? $this->turnos->liberarRecordatorio($id)
        : $this->turnos->registrarRecordatorio($id, 'error'),
    );
  }

  /** Link wa.me con el recordatorio armado (botón manual, sin API). Registra el aviso. */
  public function whatsappManual(int $turnoId): string
  {
    $turno = $this->turno($turnoId);
    $texto = $this->renderizar($this->plantilla('whatsapp_recordatorio'), $this->variablesTurno($turno));
    $this->turnos->registrarRecordatorio($turnoId, 'whatsapp');
    $this->registro->registrar($turnoId, self::RECORDATORIO, 'whatsapp_manual', (string) $turno['cliente_telefono'], 'enviado');

    return whatsapp_url((string) $turno['cliente_telefono'], $texto, $this->configuracion->seccion('notificaciones')['codigo_pais']);
  }

  // ---------- Órdenes ----------

  /**
   * Envía el presupuesto con el PDF adjunto y el link para aceptarlo o rechazarlo.
   *
   * @return string|null canal usado, o null si el cliente no tiene contacto
   */
  public function enviarPresupuesto(int $ordenId): ?string
  {
    return $this->enviarDocumentoPresupuesto(self::PRESUPUESTO, $ordenId);
  }

  /**
   * Avisa que cambió un presupuesto que el cliente ya había aceptado: va el PDF nuevo y el
   * link para volver a aceptarlo. Cuenta como un envío, así que renueva la vigencia.
   *
   * @return string|null canal usado, o null si el cliente no tiene contacto
   */
  public function enviarPresupuestoModificado(int $ordenId): ?string
  {
    return $this->enviarDocumentoPresupuesto(self::PRESUPUESTO_MODIFICADO, $ordenId);
  }

  private function enviarDocumentoPresupuesto(string $tipo, int $ordenId): ?string
  {
    $orden = $this->orden($ordenId);
    (new Validator())
      ->check(OrdenService::editable(EstadoOrden::from($orden['estado'])), 'Solo se puede enviar el presupuesto de órdenes pendientes o en proceso.')
      ->validate();
    $pdf = $this->documentos->pdf($ordenId);

    $canal = $this->enviarMensaje(
      $tipo,
      $this->destinatario($orden),
      $this->variablesOrden($orden),
      ordenId: $ordenId,
      adjuntos: [['nombre' => $pdf['nombre'], 'contenido' => $pdf['contenido'], 'tipo' => 'application/pdf']],
    );

    if ($canal !== null) {
      $this->ordenes->registrarPresupuestoEnviado($ordenId);
    }

    return $canal;
  }

  /** ¿Corresponde avisar "equipo listo"? Activado en la configuración y con un canal para enviarlo. */
  public function avisoListoActivo(): bool
  {
    return (bool) $this->configuracion->seccion('notificaciones')['avisar_listo'] && $this->hayCanalDisponible();
  }

  /** ¿Ya se le avisó al cliente que esta orden está lista? (Si la orden vuelve atrás y se finaliza de nuevo, no se repite.) */
  public function equipoListoAvisado(int $ordenId): bool
  {
    return $this->registro->enviadoDeOrden($ordenId, self::LISTO);
  }

  /**
   * Le avisa al cliente que el equipo está listo para retirar, con el saldo a abonar.
   *
   * @return string|null canal usado, o null si el cliente no tiene contacto
   */
  public function avisarEquipoListo(int $ordenId): ?string
  {
    $orden = $this->orden($ordenId);

    return $this->enviarMensaje(self::LISTO, $this->destinatario($orden), $this->variablesOrden($orden), ordenId: $ordenId);
  }

  /**
   * Avisos automáticos de próximo mantenimiento: mismas condiciones de horario que
   * los recordatorios, para los mantenimientos que vencen dentro de los días de
   * anticipación configurados.
   *
   * @return array{enviados: int, sin_contacto: int, errores: int, omitido: ?string}
   */
  public function enviarAvisosMantenimiento(DateTimeImmutable $ahora): array
  {
    $config = $this->configuracion->seccion('mantenimiento');
    $omitido = $this->motivoParaNoEnviar($ahora, (bool) $config['aviso_automatico'], 'Aviso de mantenimiento desactivado.');
    if ($omitido !== null) {
      return ['enviados' => 0, 'sin_contacto' => 0, 'errores' => 0, 'omitido' => $omitido];
    }

    $desde = $ahora->format('Y-m-d');
    $hasta = $ahora->modify("+{$config['aviso_dias_antes']} days")->format('Y-m-d');

    return $this->procesarLote(
      $this->ordenes->mantenimientosParaAvisar($desde, $hasta),
      reservar: fn(int $id) => $this->ordenes->reservarAvisoMantenimiento($id),
      enviar: function (int $id) {
        $orden = $this->orden($id);

        return $this->enviarMensaje(self::MANTENIMIENTO, $this->destinatario($orden), $this->variablesOrden($orden), ordenId: $id);
      },
      registrar: fn() => null,
      liberar: fn(int $id) => $this->registro->erroresRecientesDeOrden($id, self::MANTENIMIENTO) < self::MAX_ERRORES
        ? $this->ordenes->liberarAvisoMantenimiento($id)
        : null,
    );
  }

  /** Aviso interno al email del negocio (p. ej. respuesta de un presupuesto). No interrumpe si falla. */
  public function avisarNegocio(string $asunto, string $texto): void
  {
    $negocio = $this->configuracion->seccion('negocio');
    if (!$this->configuracion->seccion('notificaciones')['avisar_negocio']) {
      return;
    }

    $email = $this->canal('email');
    $destinatario = new Destinatario($negocio['nombre'], $negocio['email']);
    if (!$email->disponible() || !$email->puedeEnviarA($destinatario)) {
      return;
    }

    try {
      $email->enviar($destinatario, new Mensaje($asunto, $texto));
    } catch (Throwable $e) {
      $this->logger->warning('No se pudo avisar al negocio por email', ['exception' => $e]);
    }
  }

  // ---------- Plantillas ----------

  /** Reemplaza {variable} por su valor; las desconocidas quedan como están. */
  public function renderizar(string $plantilla, array $variables): string
  {
    $reemplazos = [];
    foreach ($variables as $clave => $valor) {
      $reemplazos['{' . $clave . '}'] = (string) $valor;
    }

    return strtr($plantilla, $reemplazos);
  }

  /** @return array<string, string> */
  public function variablesTurno(array $turno): array
  {
    return [
      ...$this->variablesGenerales($turno),
      'fecha' => format_date($turno['fecha']),
      'hora' => substr((string) $turno['hora'], 0, 5),
      'link_turno' => absolute_url('turno/' . $this->turnos->token((int) $turno['id'])),
    ];
  }

  /** @return array<string, string> */
  public function variablesOrden(array $orden): array
  {
    return [
      ...$this->variablesGenerales($orden),
      'numero' => str_pad((string) $orden['id'], 4, '0', STR_PAD_LEFT),
      'total' => money($orden['total']),
      'saldo' => money(max(0, (float) $orden['total'] - $this->pagos->totalPagado((int) $orden['id']))),
      'link_presupuesto' => absolute_url('presupuesto/' . $this->ordenes->token((int) $orden['id'])),
      'fecha_proximo' => $orden['proximo_mantenimiento_fecha'] ? format_date($orden['proximo_mantenimiento_fecha']) : '—',
    ];
  }

  // ---------- Internos ----------

  /** @return array<string, string> variables comunes a todos los avisos */
  private function variablesGenerales(array $fila): array
  {
    $negocio = $this->configuracion->seccion('negocio');

    return [
      'cliente' => mb_convert_case(mb_strtolower((string) $fila['cliente_nombre']), MB_CASE_TITLE),
      'equipo' => equipo_texto($fila, false),
      'negocio' => $negocio['nombre'],
      'direccion' => $negocio['direccion'],
      'telefono' => $negocio['telefono'],
      'link_seguimiento' => absolute_url('seguimiento'),
    ];
  }

  private function enviarTurno(string $tipo, int $turnoId): ?string
  {
    $turno = $this->turno($turnoId);

    return $this->enviarMensaje($tipo, $this->destinatario($turno), $this->variablesTurno($turno), turnoId: $turnoId);
  }

  /**
   * Envía por el primer canal disponible para el destinatario y registra el resultado.
   *
   * @param list<array{nombre: string, contenido: string, tipo: string}> $adjuntos
   * @return string|null canal usado o null si no hay canal posible para el destinatario
   * @throws RuntimeException si el envío falla (queda registrado)
   */
  private function enviarMensaje(string $tipo, Destinatario $destinatario, array $variables, ?int $turnoId = null, ?int $ordenId = null, array $adjuntos = []): ?string
  {
    foreach ($this->canalesDisponibles() as $canal) {
      if (!$canal->puedeEnviarA($destinatario)) {
        continue;
      }

      $mensaje = new Mensaje(
        $this->renderizar($this->plantilla("{$canal->nombre()}_{$tipo}_asunto", ''), $variables),
        $this->renderizar($this->plantilla("{$canal->nombre()}_{$tipo}"), $variables),
        $adjuntos,
      );

      try {
        $canal->enviar($destinatario, $mensaje);
      } catch (Throwable $e) {
        $this->logger->error('Falló el envío de {tipo} por {canal}', [
          'tipo' => $tipo, 'canal' => $canal->nombre(), 'turno' => $turnoId, 'orden' => $ordenId, 'exception' => $e,
        ]);
        $this->registro->registrar($turnoId, $tipo, $canal->nombre(), $canal->destino($destinatario), 'error', $e->getMessage(), $ordenId);
        throw new RuntimeException("No se pudo enviar el aviso por {$canal->nombre()}. Revisá la configuración del servidor de correo.", 0, $e);
      }

      $this->registro->registrar($turnoId, $tipo, $canal->nombre(), $canal->destino($destinatario), 'enviado', null, $ordenId);
      $this->logger->info('Aviso de {tipo} enviado por {canal}', ['tipo' => $tipo, 'canal' => $canal->nombre(), 'turno' => $turnoId, 'orden' => $ordenId]);

      return $canal->nombre();
    }

    $this->registro->registrar($turnoId, $tipo, '-', '-', 'error', 'El cliente no tiene datos de contacto para los canales activos.', $ordenId);

    return null;
  }

  /**
   * Procesa avisos automáticos de a uno, reservando cada uno antes de enviarlo
   * para no duplicar si dos ejecuciones se superponen.
   *
   * @param list<int> $ids
   * @return array{enviados: int, sin_contacto: int, errores: int, omitido: null}
   */
  private function procesarLote(array $ids, callable $reservar, callable $enviar, callable $registrar, callable $liberar): array
  {
    $resultado = ['enviados' => 0, 'sin_contacto' => 0, 'errores' => 0, 'omitido' => null];

    foreach ($ids as $id) {
      if (!$reservar($id)) {
        continue;
      }

      try {
        $canal = $enviar($id);
        $registrar($id, $canal);
        $canal !== null ? $resultado['enviados']++ : $resultado['sin_contacto']++;
      } catch (Throwable) {
        $resultado['errores']++;
        $liberar($id);
      }
    }

    return $resultado;
  }

  /** null si se puede enviar ahora; si no, el motivo. */
  private function motivoParaNoEnviar(DateTimeImmutable $ahora, bool $activado, string $desactivado): ?string
  {
    return match (true) {
      !$activado => $desactivado,
      !$this->hayCanalDisponible() => 'No hay canales de notificación disponibles.',
      !$this->configuracion->horario()->abiertoAhora($ahora) => 'Fuera del horario de atención.',
      $ahora->format('H:i') < $this->configuracion->seccion('turnos')['recordatorio_hora'] => 'Todavía no es la hora de envío.',
      default => null,
    };
  }

  private function destinatario(array $fila): Destinatario
  {
    return new Destinatario(
      (string) $fila['cliente_nombre'],
      $fila['cliente_email'] ?: null,
      $fila['cliente_telefono'] ?: null,
    );
  }

  private function plantilla(string $clave, ?string $porDefecto = null): string
  {
    return $this->configuracion->seccion('mensajes')[$clave]
      ?? $porDefecto
      ?? throw new RuntimeException("Falta la plantilla de mensaje '{$clave}'.");
  }

  /** @return array<string, mixed> */
  private function turno(int $id): array
  {
    return $this->turnos->detalle($id) ?? throw new NotFoundException('Turno no encontrado.');
  }

  /** @return array<string, mixed> */
  private function orden(int $id): array
  {
    return $this->ordenes->contacto($id) ?? throw new NotFoundException('Orden no encontrada.');
  }
}
