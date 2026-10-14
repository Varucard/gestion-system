<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\MostradorService;

/** Configuración › Modo mostrador: qué equipos dejan entrar con PIN. Solo administradores. */
final class MostradorController extends Controller
{
  /** La cookie del mostrador dura un año: es el equipo, no la sesión. */
  private const VIDA_COOKIE = 365 * 24 * 3600;

  public function __construct(View $view, Session $session, private readonly MostradorService $service, private readonly Auth $auth)
  {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('mostrador/index', [
      'title' => 'Modo mostrador',
      'mostradores' => $this->service->listar(),
      'esteEquipo' => $this->service->deCookie($request->cookie(MostradorService::COOKIE)),
      'conPin' => $this->service->usuariosConPin(),
    ]);
  }

  public function habilitar(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $token = $this->service->habilitar($request->string('nombre'), $this->auth->id());
    } catch (ValidationException $e) {
      $this->backWithErrors('/mostrador', $e, $request);
    }

    self::cookie($token, time() + self::VIDA_COOKIE);
    $this->success('Este equipo quedó como mostrador: al salir, cada uno vuelve a entrar con su PIN.');
    $this->redirect('/mostrador');
  }

  public function quitar(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    $esteEquipo = $this->service->deCookie($request->cookie(MostradorService::COOKIE));
    $this->service->quitar($id);
    if ($esteEquipo !== null && (int) $esteEquipo['id'] === $id) {
      self::cookie('', time() - 3600);
    }
    $this->success('Ese equipo ya no es un mostrador: se entra con usuario y contraseña.');
    $this->redirect('/mostrador');
  }

  private static function cookie(string $valor, int $vence): void
  {
    if ($valor === '') {
      unset($_COOKIE[MostradorService::COOKIE]);
    } else {
      $_COOKIE[MostradorService::COOKIE] = $valor;
    }
    if (!headers_sent()) {
      setcookie(MostradorService::COOKIE, $valor, [
        'expires' => $vence, 'path' => url('/'), 'httponly' => true, 'samesite' => 'Lax', 'secure' => Request::esHttps(),
      ]);
    }
  }
}
