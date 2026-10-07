<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\NotFoundException;

/**
 * Router sencillo basado en patrones del tipo `/clientes/{id}/editar`.
 *
 * Parámetros: `{id}` solo dígitos (se pasa como int), `{x:slug}` letras
 * minúsculas y guion bajo, `{x:token}` 64 caracteres hexadecimales (se pasan como string).
 *
 * Cada ruta tiene un nivel de acceso (ACCESO_*) que se valida con el guard
 * antes de ejecutar el controlador.
 */
final class Router
{
  public const ACCESO_PUBLICO = 'publico';
  public const ACCESO_USUARIO = 'usuario';
  public const ACCESO_ADMIN = 'administrador';

  private const TIPOS = [
    'slug' => '[a-z_]+',
    'token' => '[a-f0-9]{64}',
  ];

  /** @var list<array{method: string, regex: string, handler: array{0: class-string, 1: string}, acceso: string, texto: list<string>}> */
  private array $routes = [];

  /** @var (callable(string, Request): void)|null */
  private $guard = null;

  public function __construct(private readonly Container $container)
  {
  }

  /** @param callable(string $acceso, Request $request): void $guard */
  public function setGuard(callable $guard): void
  {
    $this->guard = $guard;
  }

  /** @param array{0: class-string, 1: string} $handler */
  public function get(string $pattern, array $handler, string $acceso = self::ACCESO_USUARIO): void
  {
    $this->add('GET', $pattern, $handler, $acceso);
  }

  /** @param array{0: class-string, 1: string} $handler */
  public function post(string $pattern, array $handler, string $acceso = self::ACCESO_USUARIO): void
  {
    $this->add('POST', $pattern, $handler, $acceso);
  }

  /** @param array{0: class-string, 1: string} $handler */
  private function add(string $method, string $pattern, array $handler, string $acceso): void
  {
    $texto = [];
    $regex = preg_replace_callback('#\{(\w+)(?::(\w+))?\}#', function (array $m) use (&$texto) {
      if (isset($m[2])) {
        $texto[] = $m[1];

        return '(?P<' . $m[1] . '>' . self::TIPOS[$m[2]] . ')';
      }

      return '(?P<' . $m[1] . '>\d+)';
    }, rtrim($pattern, '/') ?: '/');

    $this->routes[] = [
      'method' => $method,
      'regex' => '#^' . $regex . '$#',
      'handler' => $handler,
      'acceso' => $acceso,
      'texto' => $texto,
    ];
  }

  /**
   * Un HEAD se atiende con la ruta GET (los monitores de disponibilidad lo usan);
   * PHP descarta el cuerpo de la respuesta y quedan solo los encabezados.
   */
  public function dispatch(Request $request): void
  {
    $method = $request->method === 'HEAD' ? 'GET' : $request->method;

    foreach ($this->routes as $route) {
      if ($route['method'] !== $method || !preg_match($route['regex'], $request->path, $matches)) {
        continue;
      }

      if ($this->guard !== null) {
        ($this->guard)($route['acceso'], $request);
      }

      $params = [];
      foreach (array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY) as $nombre => $valor) {
        $params[$nombre] = in_array($nombre, $route['texto'], true) ? $valor : (int) $valor;
      }
      [$class, $action] = $route['handler'];

      $controller = $this->container->get($class);
      $controller->$action($request, ...$params);

      return;
    }

    throw new NotFoundException('La página solicitada no existe.');
  }
}
