<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Support\ImageUpload;
use App\Support\Manual;

/**
 * Ayuda: el manual de usuario dentro del sistema. Sale de docs/manual/manual.html, la misma
 * fuente que el PDF, así que no hay dos textos que mantener (ver App\Support\Manual).
 */
final class AyudaController extends Controller
{
  private readonly Manual $manual;
  private readonly string $capturas;

  public function __construct(View $view, Session $session)
  {
    parent::__construct($view, $session);
    $docs = App::instance()->rootPath . '/docs/manual';
    $this->manual = new Manual($docs . '/manual.html');
    $this->capturas = $docs . '/capturas';
  }

  public function index(Request $request): void
  {
    $this->view->script('ayuda.js');
    $this->render('ayuda/index', [
      'title' => 'Ayuda',
      'capitulos' => $this->manual->capitulos($this->urlCaptura(...)),
      'volver' => self::volver($request->query('desde'), $request->query('pantalla'), App::instance()->basePath),
    ]);
  }

  /**
   * La pantalla de la que se vino (botón "Volver"), o null. Solo una dirección de este mismo
   * sistema: un "desde" armado a mano (https://otro-sitio, //otro-sitio) no se ofrece.
   *
   * @param string $basePath  la de App (vacía si el sistema está en la raíz del dominio)
   * @return array{url: string, pantalla: string}|null
   */
  public static function volver(mixed $desde, mixed $pantalla, string $basePath): ?array
  {
    $base = $basePath . '/';

    if (!is_string($desde) || !str_starts_with($desde, $base) || preg_match('#^/[/\\\\]|[\\x00-\\x1f\\\\]#', $desde)) {
      return null;
    }

    $pantalla = is_string($pantalla) ? trim($pantalla) : '';

    return ['url' => $desde, 'pantalla' => mb_substr($pantalla !== '' ? $pantalla : 'la pantalla anterior', 0, 60)];
  }

  /** El manual con el formato de A4, para imprimirlo o guardarlo como PDF. */
  public function imprimir(Request $request): void
  {
    header('Content-Type: text/html; charset=utf-8');
    echo $this->manual->paraImprimir($this->urlCaptura(...), asset('img/logo.png'), version_app());
  }

  /** Las capturas viven en docs/, fuera de public/: se sirven desde acá. */
  public function captura(Request $request, string $nombre): void
  {
    $archivo = "{$this->capturas}/{$nombre}.png";

    if (!is_file($archivo)) {
      throw new NotFoundException('La captura no existe.');
    }

    ImageUpload::enviar($archivo);
  }

  private function urlCaptura(string $nombre): string
  {
    $archivo = "{$this->capturas}/{$nombre}.png";

    // Como asset(): si se rehace la captura cambia la URL y el navegador no muestra la vieja.
    return url('ayuda/capturas/' . $nombre) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
  }
}
