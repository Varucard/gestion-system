<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\ConfiguracionService;

/**
 * Manifiestos de la app instalable (PWA). Son dos: el del sistema interno, que abre
 * en el inicio, y el del portal de clientes, que abre en "Seguí tu equipo". Se
 * generan acá y no como archivo fijo porque el nombre del negocio es configurable.
 */
final class PwaController extends Controller
{
  public function __construct(View $view, Session $session, private readonly ConfiguracionService $configuracion)
  {
    parent::__construct($view, $session);
  }

  public function app(Request $request): void
  {
    $nombre = $this->configuracion->seccion('negocio')['nombre'];
    $this->manifiesto($nombre, $nombre, '');
  }

  public function portal(Request $request): void
  {
    $nombre = $this->configuracion->seccion('negocio')['nombre'];
    $this->manifiesto("{$nombre} - Seguí tu equipo", $nombre, 'seguimiento');
  }

  private function manifiesto(string $nombre, string $corto, string $inicio): never
  {
    $iconos = [];
    foreach ([192, 512] as $lado) {
      $iconos[] = ['src' => asset("img/icono-{$lado}.png"), 'sizes' => "{$lado}x{$lado}", 'type' => 'image/png', 'purpose' => 'any'];
    }
    $iconos[] = ['src' => asset('img/icono-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];

    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode([
      'name' => $nombre,
      'short_name' => mb_strlen($corto) <= 15 ? $corto : explode(' ', $corto)[0],
      'lang' => 'es-AR',
      'start_url' => url($inicio),
      'scope' => url('/'),
      'display' => 'standalone',
      'background_color' => '#f8f9fa',
      'theme_color' => '#f8f9fa',
      'icons' => $iconos,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}
