<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Rol;
use App\Exceptions\CuentaBloqueadaException;
use App\Exceptions\ValidationException;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;

final class UsuarioController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly UsuarioService $service,
    private readonly UsuarioRepository $usuarios,
    private readonly Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('usuarios/index', ['title' => 'Usuarios', 'usuarios' => $this->usuarios->all()]);
  }

  public function create(Request $request): void
  {
    $this->render('usuarios/form', ['title' => 'Nuevo usuario', 'usuario' => null, 'roles' => Rol::cases()]);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->crear($request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/usuarios/crear', $e, $request);
    }

    $this->success('Usuario creado.');
    $this->redirect('/usuarios');
  }

  public function edit(Request $request, int $id): void
  {
    $this->render('usuarios/form', [
      'title' => 'Editar usuario',
      'usuario' => $this->service->obtener($id),
      'roles' => Rol::cases(),
    ]);
  }

  public function update(Request $request, int $id): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->actualizar($id, $request->all(), (int) $this->auth->id());
    } catch (ValidationException $e) {
      $this->backWithErrors("/usuarios/{$id}/editar", $e, $request);
    }

    $this->auth->refrescar($this->service->obtener($id));
    // Si cambió su propia contraseña desde acá, esta sesión sigue (las otras suyas se cortan).
    if ($id === $this->auth->id() && $request->input('clave', '') !== '') {
      $this->auth->renovarHuella();
    }
    $this->success('Usuario actualizado.');
    $this->redirect('/usuarios');
  }

  public function claveForm(Request $request): void
  {
    $this->render('usuarios/clave', ['title' => 'Mi contraseña y PIN', 'usuario' => $this->service->obtener((int) $this->auth->id())]);
  }

  /** PIN para el modo mostrador. */
  public function definirPin(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->definirPin((int) $this->auth->id(), (string) $request->input('clave_actual_pin', ''), $request->string('pin'), $request->string('pin_confirmacion'), Request::ip());
      $this->success('Listo: ya podés entrar con tu PIN en los equipos habilitados como mostrador.');
    } catch (CuentaBloqueadaException) {
      $this->cerrarPorBloqueo();
    } catch (ValidationException $e) {
      // Los errores van dentro del recuadro del PIN (abajo de la página), no arriba de todo.
      foreach ($e->errors() as $mensaje) {
        $this->session->flash('error_pin', $mensaje);
      }
    }

    $this->redirect('/perfil/clave#pin');
  }

  public function quitarPin(Request $request): void
  {
    $this->verifyCsrf($request);

    $this->service->quitarPin((int) $this->auth->id());
    $this->success('Se quitó tu PIN: en el mostrador vas a entrar con usuario y contraseña.');
    $this->redirect('/perfil/clave');
  }

  public function cambiarClave(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->cambiarClave(
        (int) $this->auth->id(),
        (string) $request->input('clave_actual', ''),
        (string) $request->input('clave', ''),
        (string) $request->input('clave_confirmacion', ''),
        Request::ip(),
      );
    } catch (CuentaBloqueadaException) {
      $this->cerrarPorBloqueo();
    } catch (ValidationException $e) {
      foreach ($e->errors() as $mensaje) {
        $this->error($mensaje);
      }
      $this->redirect('/perfil/clave');
    }

    // Esta sesión sigue abierta; las otras de este usuario (otra PC, el celular) se cortan.
    $this->auth->renovarHuella();
    $this->success('Contraseña actualizada.');
    $this->redirect('/');
  }

  /** Demasiadas contraseñas actuales equivocadas: alguien está probando con la sesión abierta. */
  private function cerrarPorBloqueo(): never
  {
    $this->auth->logout();
    $this->error(sprintf('Demasiadas contraseñas equivocadas: por seguridad se cerró la sesión. Esperá %d minutos para volver a entrar.', UsuarioService::MINUTOS_BLOQUEO));
    $this->redirect('/login');
  }
}
