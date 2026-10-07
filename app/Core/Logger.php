<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use Stringable;
use Throwable;

/**
 * Logger de la aplicación (API compatible con PSR-3).
 *
 * Escribe una línea JSON por evento en storage/logs/app-AAAA-MM-DD.log, con
 * nivel, mensaje, contexto y datos de la petición (id, usuario, IP, ruta).
 * Nunca lanza excepciones: si no puede escribir, recurre a error_log().
 *
 *   $logger->info('Pago registrado en la orden {orden}', ['orden' => 12]);
 *   $logger->error('Falló el envío', ['exception' => $e]);
 */
final class Logger
{
  public const NIVELES = [
    'debug' => 100,
    'info' => 200,
    'notice' => 250,
    'warning' => 300,
    'error' => 400,
    'critical' => 500,
  ];

  private readonly int $minimo;

  /** @var array<string, mixed> datos que se agregan a todas las líneas */
  private array $global = [];

  public function __construct(
    private readonly string $directorio,
    string $nivelMinimo = 'info',
  ) {
    $this->minimo = self::NIVELES[$nivelMinimo] ?? self::NIVELES['info'];
  }

  /** Datos comunes a todos los eventos de la petición (request_id, usuario, ip, ruta). */
  public function agregarContexto(array $datos): void
  {
    $this->global = array_replace($this->global, array_filter($datos, fn($v) => $v !== null));
  }

  public function contexto(string $clave): mixed
  {
    return $this->global[$clave] ?? null;
  }

  public function debug(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('debug', $mensaje, $contexto);
  }

  public function info(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('info', $mensaje, $contexto);
  }

  public function notice(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('notice', $mensaje, $contexto);
  }

  public function warning(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('warning', $mensaje, $contexto);
  }

  public function error(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('error', $mensaje, $contexto);
  }

  public function critical(string|Stringable $mensaje, array $contexto = []): void
  {
    $this->log('critical', $mensaje, $contexto);
  }

  public function log(string $nivel, string|Stringable $mensaje, array $contexto = []): void
  {
    if ((self::NIVELES[$nivel] ?? 0) < $this->minimo) {
      return;
    }

    $ahora = new DateTimeImmutable();
    $linea = [
      'fecha' => $ahora->format(DATE_ATOM),
      'nivel' => $nivel,
      'mensaje' => self::interpolar((string) $mensaje, $contexto),
      ...$this->global,
    ];

    if (isset($contexto['exception']) && $contexto['exception'] instanceof Throwable) {
      $linea['excepcion'] = self::excepcion($contexto['exception']);
      unset($contexto['exception']);
    }
    if ($contexto !== []) {
      $linea['contexto'] = self::normalizar($contexto);
    }

    try {
      if (!is_dir($this->directorio)) {
        @mkdir($this->directorio, 0775, true);
      }
      $json = json_encode($linea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
      if (@file_put_contents($this->archivo($ahora->format('Y-m-d')), $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log("[{$nivel}] {$linea['mensaje']}");
      }
    } catch (Throwable) {
      error_log("[{$nivel}] {$linea['mensaje']}");
    }
  }

  /** @return list<string> fechas (AAAA-MM-DD) con archivo de log, de la más reciente a la más vieja */
  public function fechas(): array
  {
    $fechas = array_map(
      fn(string $f) => substr(basename($f, '.log'), 4),
      glob($this->directorio . '/app-????-??-??.log') ?: []
    );
    rsort($fechas);

    return $fechas;
  }

  /**
   * Últimos eventos de un día, del más nuevo al más viejo.
   *
   * @return list<array<string, mixed>>
   */
  public function leer(string $fecha, string $nivelMinimo = 'debug', string $buscar = '', int $limite = 500): array
  {
    $archivo = $this->archivo($fecha);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !is_file($archivo)) {
      return [];
    }

    $minimo = self::NIVELES[$nivelMinimo] ?? 0;
    $eventos = [];

    foreach (array_reverse(file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $linea) {
      $evento = json_decode($linea, true);
      if (!is_array($evento) || (self::NIVELES[$evento['nivel'] ?? ''] ?? 0) < $minimo) {
        continue;
      }
      if ($buscar !== '' && mb_stripos($linea, $buscar) === false) {
        continue;
      }
      $eventos[] = $evento;
      if (count($eventos) >= $limite) {
        break;
      }
    }

    return $eventos;
  }

  /** Borra los archivos más viejos que $dias. Devuelve cuántos borró. */
  public function purgar(int $dias): int
  {
    $limite = (new DateTimeImmutable("-{$dias} days"))->format('Y-m-d');
    $borrados = 0;

    foreach ($this->fechas() as $fecha) {
      if ($fecha < $limite && @unlink($this->archivo($fecha))) {
        $borrados++;
      }
    }

    return $borrados;
  }

  private function archivo(string $fecha): string
  {
    return "{$this->directorio}/app-{$fecha}.log";
  }

  /** Reemplaza {clave} del mensaje por valores escalares del contexto (PSR-3). */
  private static function interpolar(string $mensaje, array $contexto): string
  {
    $reemplazos = [];
    foreach ($contexto as $clave => $valor) {
      if (is_scalar($valor) || $valor instanceof Stringable || $valor === null) {
        $reemplazos['{' . $clave . '}'] = (string) $valor;
      }
    }

    return strtr($mensaje, $reemplazos);
  }

  /** @return array<string, mixed> */
  private static function excepcion(Throwable $e): array
  {
    return [
      'clase' => $e::class,
      'mensaje' => $e->getMessage(),
      'archivo' => $e->getFile() . ':' . $e->getLine(),
      'traza' => mb_substr($e->getTraceAsString(), 0, 4000),
      'previa' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
    ];
  }

  private static function normalizar(mixed $valor, int $profundidad = 0): mixed
  {
    return match (true) {
      $profundidad > 4 => '…',
      is_array($valor) => array_map(fn($v) => self::normalizar($v, $profundidad + 1), $valor),
      $valor instanceof Throwable => self::excepcion($valor),
      $valor instanceof Stringable => (string) $valor,
      is_object($valor) => $valor::class,
      is_resource($valor) => 'resource',
      default => $valor,
    };
  }
}
