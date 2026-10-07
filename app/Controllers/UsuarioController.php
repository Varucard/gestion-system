<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\Rol;
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
    $this->success('Usuario actualizado.');
    $this->redirect('/usuarios');
  }

  public function claveForm(Request $request): void
  {
    $this->render('usuarios/clave', ['title' => 'Cambiar contraseña']);
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
      );
    } catch (ValidationException $e) {
      foreach ($e->errors() as $mensaje) {
        $this->error($mensaje);
      }
      $this->redirect('/perfil/clave');
    }

    $this->success('Contraseña actualizada.');
    $this->redirect('/');
  }
}
