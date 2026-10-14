<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BusquedaRepository;

final class BusquedaController extends Controller
{
  public function __construct(View $view, Session $session, private readonly BusquedaRepository $busqueda)
  {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $texto = trim((string) $request->query('q', ''));
    $resultados = $this->resultados($texto);

    // Un único resultado: directo a su ficha.
    $total = array_sum(array_map('count', $resultados));
    if ($total === 1) {
      $tipo = array_key_first(array_filter($resultados));
      $this->redirect(match ($tipo) {
        'clientes' => "/clientes/{$resultados['clientes'][0]['id']}",
        'equipos' => "/equipos/{$resultados['equipos'][0]['id']}",
        'ordenes' => "/ordenes/{$resultados['ordenes'][0]['id']}",
      });
    }

    $this->render('busqueda/index', ['title' => 'Búsqueda', 'texto' => $texto, 'resultados' => $resultados, 'total' => $total]);
  }

  /**
   * JSON: las primeras coincidencias mientras se escribe en el buscador (app.js las muestra
   * debajo). Lo mínimo para reconocer cada una: nada de teléfonos ni emails.
   */
  public function sugerencias(Request $request): void
  {
    $r = $this->resultados(trim((string) $request->query('q', '')));
    $sugerencias = [
      ...array_map(fn(array $o) => ['tipo' => 'Orden', 'texto' => "Orden #{$o['id']}", 'detalle' => equipo_texto($o, false) . " · {$o['cliente']}", 'url' => url("ordenes/{$o['id']}")], $r['ordenes']),
      ...array_map(fn(array $v) => ['tipo' => 'Equipo', 'texto' => equipo_texto($v, false), 'detalle' => ($v['numero_serie'] ? "S/N {$v['numero_serie']} · " : '') . $v['cliente'], 'url' => url("equipos/{$v['id']}")], $r['equipos']),
      ...array_map(fn(array $c) => ['tipo' => 'Cliente', 'texto' => "{$c['apellido']}, {$c['nombre']}", 'detalle' => "DNI {$c['dni']}", 'url' => url("clientes/{$c['id']}")], $r['clientes']),
    ];

    $this->json(array_slice($sugerencias, 0, 8));
  }

  /** @return array{clientes: list<array<string, mixed>>, equipos: list<array<string, mixed>>, ordenes: list<array<string, mixed>>} */
  private function resultados(string $texto): array
  {
    // Un número solo, aunque sea de un dígito ("5"), es un número de orden: se busca como "#5".
    if (ctype_digit($texto) && strlen($texto) === 1) {
      // Un dígito solo no es un DNI ni un número de serie: solo cuenta la orden con ese número.
      return ['clientes' => [], 'equipos' => [], 'ordenes' => $this->busqueda->buscar("#{$texto}")['ordenes']];
    }

    return mb_strlen($texto) >= 2 ? $this->busqueda->buscar($texto) : ['clientes' => [], 'equipos' => [], 'ordenes' => []];
  }
}
