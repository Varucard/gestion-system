<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
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
    private readonly \App\Services\MostradorService $mostrador,
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

    // En un mostrador: los nombres para tocar y el PIN (con usuario y contraseña, si se pide).
    $mostrador = $this->mostrador->deCookie($request->cookie(\App\Services\MostradorService::COOKIE));
    $conPin = $mostrador !== null ? $this->mostrador->usuariosConPin() : [];
    if ($conPin !== [] && $request->query('con_clave') !== '1') {
      echo $this->view->render('auth/mostrador', ['title' => '¿Quién sos?', 'usuarios' => $conPin, 'mostrador' => $mostrador], 'layouts/guest');

      return;
    }

    echo $this->view->render('auth/login', ['title' => 'Iniciar sesión', 'mostrador' => $conPin !== []], 'layouts/guest');
  }

  /** Entrada con PIN, solo desde un navegador habilitado como mostrador. */
  public function loginPin(Request $request): void
  {
    $this->verifyCsrf($request);

    $mostrador = $this->mostrador->deCookie($request->cookie(\App\Services\MostradorService::COOKIE));
    if ($mostrador === null) {
      $this->error('Este equipo no está habilitado como mostrador: entrá con tu usuario y contraseña.');
      $this->redirect('/login');
    }

    try {
      $usuario = $this->mostrador->entrar($mostrador, (int) $request->int('usuario_id'), (string) $request->input('pin', ''), Request::ip());
    } catch (ValidationException $e) {
      // Queda elegida la misma persona: solo tiene que volver a escribir el PIN.
      $this->session->keepInput(['usuario_id' => (string) $request->int('usuario_id')]);
      $this->error($e->getMessage());
      $this->redirect('/login');
    }

    $this->auth->login($usuario, mostrador: true);
    $this->auditor->registrar('login', 'usuario', (int) $usuario['id'], "{$usuario['usuario']} entró con PIN en \"{$mostrador['nombre']}\"");
    // Siempre al inicio: si no, el que entra cae en la última pantalla que miraba el anterior.
    $this->session->pullIntended('/');
    $this->redirect('/');
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
      // En un mostrador, de vuelta al formulario de contraseña (no a "¿Quién sos?").
      $this->redirect($this->mostrador->deCookie($request->cookie(\App\Services\MostradorService::COOKIE)) !== null ? '/login?con_clave=1' : '/login');
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

  /**
   * Alta del primer administrador: solo mientras no haya usuarios, y con el código de
   * INSTALACION_TOKEN del .env (p. ej. el de `openssl rand -hex 16`). Sin código, la pantalla
   * explica cómo crearlo por consola (bin/usuario.php crear): si no, el primero que llegara a la
   * dirección pública antes que el dueño se quedaría con el sistema.
   */
  public function setupForm(Request $request): void
  {
    $this->soloSinUsuarios();

    echo $this->view->render('auth/instalacion', ['title' => 'Primer ingreso', 'conToken' => self::tokenInstalacion() !== ''], 'layouts/guest');
  }

  public function setup(Request $request): void
  {
    $this->verifyCsrf($request);
    $this->soloSinUsuarios();

    $token = self::tokenInstalacion();
    if ($token === '') {
      $this->redirect('/instalacion');
    }
    if (!hash_equals($token, (string) $request->input('token_instalacion', ''))) {
      $this->backWithErrors('/instalacion', new ValidationException(['El código de instalación no es correcto.'], ['token_instalacion' => 'El código de instalación no es correcto.']), $request);
    }

    try {
      $id = $this->usuarios->crearPrimerAdministrador($request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/instalacion', $e, $request);
    }

    $this->auth->login($this->usuarios->obtener($id));
    $this->success('Administrador creado. ¡Bienvenido! Seguí estos pasos para dejar el sistema listo.');
    $this->redirect('/primeros-pasos');
  }

  private static function tokenInstalacion(): string
  {
    return trim((string) Env::get('INSTALACION_TOKEN', ''));
  }

  private function soloSinUsuarios(): void
  {
    if ($this->usuarios->hayUsuarios()) {
      throw new NotFoundException('La instalación ya fue realizada.');
    }
  }
}
