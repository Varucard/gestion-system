<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envoltorio inmutable de la petición HTTP actual.
 */
final class Request
{
  /**
   * @param array<string, mixed> $query
   * @param array<string, mixed> $body
   */
  /** @param array<string, array<string, mixed>> $files */
  public function __construct(
    public readonly string $method,
    public readonly string $path,
    private readonly array $query,
    private readonly array $body,
    private readonly bool $ajax = false,
    private readonly array $files = [],
  ) {
  }

  public static function fromGlobals(string $basePath): self
  {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if ($basePath !== '' && str_starts_with($path, $basePath)) {
      $path = substr($path, strlen($basePath));
    }

    $path = '/' . trim(rawurldecode($path), '/');

    return new self(
      strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
      $path,
      $_GET,
      $_POST,
      ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest',
      $_FILES,
    );
  }

  public function isAjax(): bool
  {
    return $this->ajax;
  }

  /** Valor de la query string o del cuerpo (en ese orden de prioridad: cuerpo, query). */
  public function input(string $key, mixed $default = null): mixed
  {
    return $this->body[$key] ?? $this->query[$key] ?? $default;
  }

  /** Archivo subido (formato de $_FILES) o null si no se envió ninguno. */
  public function file(string $name): ?array
  {
    $file = $this->files[$name] ?? null;

    return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
  }

  public function header(string $name): ?string
  {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
  }

  /** @return array<string, mixed> */
  public function queryAll(): array
  {
    return $this->query;
  }

  public function query(string $key, mixed $default = null): mixed
  {
    return $this->query[$key] ?? $default;
  }

  /** @return array<string, mixed> */
  public function all(): array
  {
    return $this->body;
  }

  public function string(string $key): string
  {
    $value = $this->input($key, '');

    return is_scalar($value) ? trim((string) $value) : '';
  }

  public function int(string $key): ?int
  {
    $value = filter_var($this->input($key), FILTER_VALIDATE_INT);

    return $value === false ? null : $value;
  }

  /** @return list<int> */
  public function intList(string $key): array
  {
    $values = $this->input($key, []);
    if (!is_array($values)) {
      return [];
    }

    $ids = array_filter(array_map('intval', $values), fn(int $id) => $id > 0);

    return array_values(array_unique($ids));
  }

  /**
   * IP real del cliente. Si la petición llega desde un proxy de confianza (TRUSTED_PROXIES:
   * IPs o rangos CIDR separados por coma, por ejemplo el nginx o Cloudflare delante del
   * sistema), se toma de X-Forwarded-For la última IP que no sea de un proxy de confianza.
   * Sin proxies configurados se usa REMOTE_ADDR: así nadie puede inventarse la IP mandando
   * la cabecera a mano.
   *
   * @param array<string, mixed>|null $server por defecto $_SERVER
   */
  public static function ip(?array $server = null, ?string $proxies = null): string
  {
    $server ??= $_SERVER;
    $confianza = array_filter(array_map('trim', explode(',', $proxies ?? (string) Env::get('TRUSTED_PROXIES', ''))));
    $ip = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($confianza === [] || !self::enRangos($ip, $confianza)) {
      return $ip;
    }

    $cadena = array_reverse(array_filter(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')))));
    foreach ($cadena as $salto) {
      if (filter_var($salto, FILTER_VALIDATE_IP) === false) {
        break;
      }
      $ip = $salto;
      if (!self::enRangos($salto, $confianza)) {
        break;
      }
    }

    return $ip;
  }

  /** ¿La petición llegó por HTTPS? (directamente o a través de un proxy de confianza) */
  public static function esHttps(?array $server = null, ?string $proxies = null): bool
  {
    $server ??= $_SERVER;
    if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
      return true;
    }
    $confianza = array_filter(array_map('trim', explode(',', $proxies ?? (string) Env::get('TRUSTED_PROXIES', ''))));

    return $confianza !== []
      && self::enRangos((string) ($server['REMOTE_ADDR'] ?? ''), $confianza)
      && strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
  }

  /** @param list<string> $rangos IPs sueltas o rangos CIDR (IPv4 o IPv6) */
  private static function enRangos(string $ip, array $rangos): bool
  {
    $binaria = @inet_pton($ip);
    if ($binaria === false) {
      return false;
    }

    foreach ($rangos as $rango) {
      [$red, $bits] = str_contains($rango, '/') ? explode('/', $rango, 2) : [$rango, null];
      $redBinaria = @inet_pton($red);
      if ($redBinaria === false || strlen($redBinaria) !== strlen($binaria)) {
        continue;
      }
      $bits = $bits === null ? strlen($binaria) * 8 : max(0, min((int) $bits, strlen($binaria) * 8));
      $bytes = intdiv($bits, 8);
      $resto = $bits % 8;
      if (substr($binaria, 0, $bytes) !== substr($redBinaria, 0, $bytes)) {
        continue;
      }
      if ($resto === 0 || ((ord($binaria[$bytes]) ^ ord($redBinaria[$bytes])) & (0xFF << (8 - $resto)) & 0xFF) === 0) {
        return true;
      }
    }

    return false;
  }
}
