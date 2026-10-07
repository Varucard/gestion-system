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
    $resultados = mb_strlen($texto) >= 2 ? $this->busqueda->buscar($texto) : ['clientes' => [], 'equipos' => [], 'ordenes' => []];

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
}
