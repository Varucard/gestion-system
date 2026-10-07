<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Exceptions\ValidationException;
use App\Support\HorarioAtencion;
use App\Support\Validator;
use RuntimeException;

/**
 * Configuración del sistema, organizada por secciones (ver config/negocio.php).
 *
 * Se guarda como JSON en storage/ en lugar de generar código PHP, de modo que
 * lo cargado por el usuario nunca se ejecuta.
 */
final class ConfiguracionService
{
  public const SECCIONES = ['negocio', 'trabajo', 'turnos', 'mantenimiento', 'notificaciones', 'mensajes', 'stock', 'portal', 'backups'];

  /** Variables que se pueden usar en las plantillas de mensajes, según de qué se trate el aviso. */
  public const VARIABLES_GENERALES = ['cliente', 'equipo', 'negocio', 'direccion', 'telefono', 'link_seguimiento'];
  public const VARIABLES_TURNO = ['fecha', 'hora', 'link_turno'];
  public const VARIABLES_ORDEN = ['numero', 'total', 'saldo', 'link_presupuesto', 'fecha_proximo'];

  /**
   * Variables válidas para una plantilla: las de turnos (confirmación y recordatorio) o las
   * de órdenes (presupuesto, equipo listo y mantenimiento), más las generales.
   *
   * @return list<string>
   */
  public static function variablesDe(string $campo): array
  {
    $deTurno = str_contains($campo, 'confirmacion') || str_contains($campo, 'recordatorio');

    return [...self::VARIABLES_GENERALES, ...($deTurno ? self::VARIABLES_TURNO : self::VARIABLES_ORDEN)];
  }

  private const FERIADOS_API = 'https://api.argentinadatos.com/v1/feriados/%d';

  private readonly string $defaultsFile;
  private readonly string $storageFile;
  private ?array $cache = null;

  public function __construct(?string $rootPath = null)
  {
    $rootPath ??= App::instance()->rootPath;
    $this->defaultsFile = $rootPath . '/config/negocio.php';
    $this->storageFile = $rootPath . '/storage/config/negocio.json';
  }

  /** @return array<string, array<string, mixed>> */
  public function obtener(): array
  {
    if ($this->cache !== null) {
      return $this->cache;
    }

    $config = require $this->defaultsFile;

    foreach ($this->guardada() as $seccion => $valores) {
      if (isset($config[$seccion]) && is_array($valores)) {
        $config[$seccion] = array_replace($config[$seccion], $valores);
      }
    }

    return $this->cache = $config;
  }

  /** Descarta los valores en memoria para volver a leer el archivo. */
  public function recargar(): void
  {
    $this->cache = null;
  }

  /** @return array<string, mixed> */
  public function seccion(string $seccion): array
  {
    return $this->obtener()[$seccion] ?? [];
  }

  public function horario(): HorarioAtencion
  {
    return HorarioAtencion::desdeConfig($this->seccion('turnos'));
  }

  /** @param array<string, mixed> $input */
  public function guardar(string $seccion, array $input): void
  {
    $valores = match ($seccion) {
      'negocio' => $this->validarNegocio($input),
      'trabajo' => $this->validarTrabajo($input),
      'turnos' => $this->validarTurnos($input),
      'notificaciones' => $this->validarNotificaciones($input),
      'mensajes' => $this->validarMensajes($input),
      'mantenimiento' => $this->validarMantenimiento($input),
      'stock' => $this->validarStock($input),
      'portal' => $this->validarPortal($input),
      'backups' => $this->validarBackups($input),
      default => throw new ValidationException(['Sección de configuración inválida.']),
    };

    $guardada = $this->guardada();
    $guardada[$seccion] = $valores;
    $this->escribir($guardada);
    $this->cache = null;
  }

  /**
   * Agrega a la lista de feriados los nacionales del año (fuente: ArgentinaDatos).
   * Devuelve cuántos feriados nuevos se agregaron.
   */
  public function importarFeriados(int $anio): int
  {
    $contexto = stream_context_create(['http' => ['timeout' => 10, 'header' => "Accept: application/json\r\n"]]);
    $json = @file_get_contents(sprintf(self::FERIADOS_API, $anio), false, $contexto);
    $datos = $json !== false ? json_decode($json, true) : null;

    if (!is_array($datos)) {
      throw new ValidationException(['No se pudieron obtener los feriados. Revisá la conexión a internet o cargalos a mano.']);
    }

    $fechas = array_values(array_filter(array_column($datos, 'fecha'), fn($f) => is_string($f) && Validator::fecha($f)));
    $actuales = $this->seccion('turnos')['feriados'];
    $todas = array_values(array_unique([...$actuales, ...$fechas]));
    sort($todas);

    $guardada = $this->guardada();
    $guardada['turnos'] = array_replace($this->seccion('turnos'), ['feriados' => $todas]);
    $this->escribir($guardada);
    $this->cache = null;

    return count($todas) - count($actuales);
  }

  // ---------- Validaciones por sección ----------

  private function validarNegocio(array $input): array
  {
    $t = fn(string $k) => trim((string) ($input[$k] ?? ''));
    $negocio = [
      'nombre' => $t('nombre'), 'cuit' => $t('cuit'), 'direccion' => $t('direccion'),
      'telefono' => $t('telefono'), 'whatsapp' => $t('whatsapp'), 'email' => $t('email'),
    ];

    (new Validator())
      ->check($negocio['nombre'] !== '', 'El nombre del negocio es obligatorio.')
      ->check((bool) preg_match('/^\d{2}-?\d{8}-?\d$/', $negocio['cuit']), 'El CUIT debe tener el formato 20-12345678-9.')
      ->check($negocio['direccion'] !== '', 'La dirección es obligatoria.')
      ->check($negocio['telefono'] !== '', 'El teléfono es obligatorio.')
      ->check((bool) preg_match('/^\+?\d{10,15}$/', $negocio['whatsapp']), 'El WhatsApp debe contener solo números (con + opcional).')
      ->check(filter_var($negocio['email'], FILTER_VALIDATE_EMAIL) !== false, 'El email no es válido.')
      ->validate();

    return $negocio;
  }

  private function validarTrabajo(array $input): array
  {
    $dias = fn(string $k) => (int) ($input[$k] ?? 0);
    $rango = fn(int $n) => $n >= 1 && $n <= 365;
    $trabajo = [
      'validez' => $dias('validez'),
      'garantia' => $dias('garantia'),
      'tiempo_estimado' => $dias('tiempo_estimado'),
      'forma_pago' => self::lineas($input['forma_pago'] ?? ''),
      'observaciones' => self::lineas($input['observaciones'] ?? ''),
      'mensaje_legal' => trim((string) ($input['mensaje_legal'] ?? '')),
      'aceptar_inicia_trabajo' => !empty($input['aceptar_inicia_trabajo']),
    ];

    (new Validator())
      ->check($rango($trabajo['validez']), 'La validez debe estar entre 1 y 365 días.')
      ->check($rango($trabajo['garantia']), 'La garantía debe estar entre 1 y 365 días.')
      ->check($rango($trabajo['tiempo_estimado']), 'El tiempo estimado debe estar entre 1 y 365 días.')
      ->check($trabajo['forma_pago'] !== [], 'Ingresá al menos una forma de pago.')
      ->validate();

    return $trabajo;
  }

  private function validarTurnos(array $input): array
  {
    $v = new Validator();
    $horario = [];

    foreach (array_keys(HorarioAtencion::DIAS) as $dia) {
      $datos = $input['horario'][$dia] ?? [];
      if (empty($datos['abierto'])) {
        $horario[(string) $dia] = null;
        continue;
      }

      $desde = substr(trim((string) ($datos['desde'] ?? '')), 0, 5);
      $hasta = substr(trim((string) ($datos['hasta'] ?? '')), 0, 5);
      $v->check(Validator::hora($desde) && Validator::hora($hasta) && $desde < $hasta, 'Revisá el horario del ' . mb_strtolower(HorarioAtencion::DIAS[$dia]) . ': la apertura debe ser anterior al cierre.');
      $horario[(string) $dia] = ['desde' => $desde, 'hasta' => $hasta];
    }

    $feriados = self::lineas($input['feriados'] ?? '');
    $invalidos = array_filter($feriados, fn($f) => !Validator::fecha($f));
    $cupos = (int) ($input['cupos_por_horario'] ?? 0);
    $intervalo = (int) ($input['intervalo_minutos'] ?? 60);
    $horaRecordatorio = substr(trim((string) ($input['recordatorio_hora'] ?? '')), 0, 5);

    $v->check(array_filter($horario) !== [], 'Tiene que haber al menos un día de atención.')
      ->check($invalidos === [], 'Fechas de feriados inválidas (usar AAAA-MM-DD): ' . implode(', ', $invalidos))
      ->check($cupos >= 1 && $cupos <= 20, 'Los turnos simultáneos deben estar entre 1 y 20.')
      ->check(in_array($intervalo, [15, 20, 30, 45, 60, 90, 120], true), 'El intervalo de la agenda no es válido.')
      ->check(Validator::hora($horaRecordatorio), 'La hora de envío de recordatorios no es válida.')
      ->validate();

    sort($feriados);

    return [
      'horario' => $horario,
      'validar_horario' => !empty($input['validar_horario']),
      'cupos_por_horario' => $cupos,
      'intervalo_minutos' => $intervalo,
      'feriados' => array_values(array_unique($feriados)),
      'enviar_confirmacion' => !empty($input['enviar_confirmacion']),
      'recordatorio_automatico' => !empty($input['recordatorio_automatico']),
      'recordatorio_hora' => $horaRecordatorio,
    ];
  }

  private function validarMantenimiento(array $input): array
  {
    $mantenimiento = [
      'intervalo_meses' => (int) ($input['intervalo_meses'] ?? 0),
      'aviso_automatico' => !empty($input['aviso_automatico']),
      'aviso_dias_antes' => (int) ($input['aviso_dias_antes'] ?? 0),
    ];

    (new Validator())
      ->check($mantenimiento['intervalo_meses'] >= 1 && $mantenimiento['intervalo_meses'] <= 36, 'El intervalo en meses debe estar entre 1 y 36.')
      ->check($mantenimiento['aviso_dias_antes'] >= 0 && $mantenimiento['aviso_dias_antes'] <= 60, 'Los días de anticipación deben estar entre 0 y 60.')
      ->validate();

    return $mantenimiento;
  }

  private function validarStock(array $input): array
  {
    $margen = Validator::importe((string) ($input['margen_sugerido'] ?? ''));
    (new Validator())->check($margen !== null && $margen <= 1000, 'El margen sugerido debe estar entre 0 y 1000 %.')->validate();

    return ['permitir_negativo' => !empty($input['permitir_negativo']), 'margen_sugerido' => $margen];
  }

  private function validarNotificaciones(array $input): array
  {
    $canales = array_values(array_intersect(NotificacionService::CANALES, (array) ($input['canales'] ?? [])));
    $codigo = preg_replace('/\D/', '', (string) ($input['codigo_pais'] ?? ''));

    (new Validator())
      ->check((bool) preg_match('/^\d{1,3}$/', $codigo), 'El código de país debe tener entre 1 y 3 dígitos.')
      ->validate();

    return [
      'canales' => $canales,
      'boton_whatsapp_manual' => !empty($input['boton_whatsapp_manual']),
      'codigo_pais' => $codigo,
      'avisar_negocio' => !empty($input['avisar_negocio']),
      'avisar_listo' => !empty($input['avisar_listo']),
    ];
  }

  private function validarMensajes(array $input): array
  {
    $campos = array_keys((require $this->defaultsFile)['mensajes']);
    $mensajes = [];
    $v = new Validator();

    foreach ($campos as $campo) {
      $texto = trim(str_replace("\r\n", "\n", (string) ($input[$campo] ?? '')));
      $mensajes[$campo] = $texto;

      preg_match_all('/\{(\w+)\}/', $texto, $usadas);
      $desconocidas = array_values(array_unique(array_diff($usadas[1], self::variablesDe($campo))));

      $v->check($texto !== '', 'Ningún mensaje puede quedar vacío.')
        ->check($desconocidas === [], sprintf(
          'El mensaje "%s" usa variables que no corresponden a ese aviso: {%s}.',
          $campo,
          implode('}, {', $desconocidas),
        ));
    }

    $v->check(
      str_contains($mensajes['email_confirmacion'], '{link_turno}'),
      'El email de confirmación debe incluir {link_turno} para que el cliente pueda confirmar.'
    )->check(
      str_contains($mensajes['email_presupuesto_modificado'], '{link_presupuesto}'),
      'El email de presupuesto modificado debe incluir {link_presupuesto} para que el cliente pueda volver a aceptarlo.'
    )->validate();

    return $mensajes;
  }

  private function validarBackups(array $input): array
  {
    $hora = substr(trim((string) ($input['hora'] ?? '')), 0, 5);
    $conservar = (int) ($input['conservar'] ?? 0);

    (new Validator())
      ->check(Validator::hora($hora), 'La hora del backup no es válida.')
      ->check($conservar >= 1 && $conservar <= 365, 'La cantidad de backups a conservar debe estar entre 1 y 365.')
      ->validate();

    return ['habilitado' => !empty($input['habilitado']), 'hora' => $hora, 'conservar' => $conservar];
  }

  private function validarPortal(array $input): array
  {
    $cantidad = (int) ($input['cantidad_ordenes'] ?? 0);
    (new Validator())->check($cantidad >= 1 && $cantidad <= 50, 'La cantidad de órdenes a mostrar debe estar entre 1 y 50.')->validate();

    return [
      'habilitado' => !empty($input['habilitado']),
      'requiere_orden' => !empty($input['requiere_orden']),
      'mostrar_montos' => !empty($input['mostrar_montos']),
      'cantidad_ordenes' => $cantidad,
    ];
  }

  // ---------- Persistencia ----------

  /** @return list<string> */
  private static function lineas(mixed $texto): array
  {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $texto))));
  }

  /** @return array<string, mixed> */
  private function guardada(): array
  {
    if (!is_file($this->storageFile)) {
      return [];
    }

    $datos = json_decode((string) file_get_contents($this->storageFile), true);

    return is_array($datos) ? $datos : [];
  }

  private function escribir(array $config): void
  {
    $dir = dirname($this->storageFile);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
      throw new RuntimeException("No se pudo crear el directorio {$dir}.");
    }

    // Escritura atómica: archivo temporal + rename.
    $tmp = $this->storageFile . '.tmp';
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $this->storageFile)) {
      throw new RuntimeException('No se pudo guardar la configuración.');
    }
  }
}
