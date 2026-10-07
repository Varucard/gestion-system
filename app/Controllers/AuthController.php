<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Rol;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\UsuarioService;

final class AuthController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly Auth $auth,
    private readonly UsuarioService $usuarios,
    private readonly \App\Services\Auditor $auditor,
  ) {
    parent::__construct($view, $session);
  }

  public function loginForm(Request $request): void
  {
    if (!$this->usuarios->hayUsuarios()) {
      $this->redirect('/instalacion');
    }
    if ($this->auth->check()) {
      $this->redirect('/');
    }

    echo $this->view->render('auth/login', ['title' => 'Iniciar sesión'], 'layouts/guest');
  }

  public function login(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $usuario = $this->usuarios->autenticar(
        $request->string('usuario'),
        (string) $request->input('clave', ''),
        Request::ip(),
      );
    } catch (ValidationException $e) {
      $this->session->keepInput(['usuario' => $request->string('usuario')]);
      $this->error($e->getMessage());
      $this->redirect('/login');
    }

    $this->auth->login($usuario);
    $this->auditor->registrar('login', 'usuario', (int) $usuario['id'], "{$usuario['usuario']} inició sesión");
    $this->redirect($this->session->pullIntended('/'));
  }

  public function logout(Request $request): void
  {
    $this->verifyCsrf($request);

    $this->auth->logout();
    $this->success('Cerraste sesión.');
    $this->redirect('/login');
  }

  /** Alta del primer administrador: solo disponible mientras no haya usuarios. */
  public function setupForm(Request $request): void
  {
    $this->soloSinUsuarios();

    echo $this->view->render('auth/instalacion', ['title' => 'Primer ingreso'], 'layouts/guest');
  }

  public function setup(Request $request): void
  {
    $this->verifyCsrf($request);
    $this->soloSinUsuarios();

    try {
      $id = $this->usuarios->crear($request->all(), Rol::Administrador);
    } catch (ValidationException $e) {
      $this->backWithErrors('/instalacion', $e, $request);
    }

    $this->auth->login($this->usuarios->obtener($id));
    $this->success('Administrador creado. ¡Bienvenido!');
    $this->redirect('/');
  }

  private function soloSinUsuarios(): void
  {
    if ($this->usuarios->hayUsuarios()) {
      throw new NotFoundException('La instalación ya fue realizada.');
    }
  }
}
