<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\EncuestaService;

/** Encuesta de usabilidad (SUS): la responde el equipo y el administrador ve los resultados. */
final class EncuestaController extends Controller
{
  public function __construct(View $view, Session $session, private readonly EncuestaService $service, private readonly Auth $auth)
  {
    parent::__construct($view, $session);
  }

  public function create(Request $request): void
  {
    $this->render('encuesta/form', [
      'title' => 'Encuesta: ¿qué tan fácil es el sistema?',
      'ronda' => $this->service->rondaAbierta(),
      'pendiente' => $this->service->pendientePara((int) $this->auth->id()),
      'preguntas' => EncuestaService::PREGUNTAS,
    ]);
  }

  public function store(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->responder((int) $this->auth->id(), $request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors('/encuesta', $e, $request);
    }

    $this->success('¡Gracias! Tus respuestas nos ayudan a decidir qué mejorar.');
    $this->redirect('/');
  }

  /** "Ahora no": no se vuelve a invitar en esta sesión. */
  public function despues(Request $request): void
  {
    $this->verifyCsrf($request);

    $_SESSION['encuesta_despues'] = true;
    // Una ruta de este sistema, sin la carpeta de instalación (la agrega redirect()): solo letras,
    // números, "/", "-" y "_". Así no puede llevar a otro sitio ("//x", "/\x").
    $volver = $request->string('volver');
    $this->redirect(preg_match('#^/[A-Za-z0-9/_-]*$#', $volver) ? $volver : '/');
  }

  public function resultados(Request $request): void
  {
    $this->render('encuesta/resultados', [
      'title' => 'Usabilidad del sistema',
      'ronda' => $this->service->rondaAbierta(),
      'resultados' => $this->service->resultados(),
      'preguntas' => EncuestaService::PREGUNTAS,
    ]);
  }

  public function abrir(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->abrirRonda($request->string('ronda'));
    } catch (ValidationException $e) {
      $this->backWithErrors('/usabilidad', $e, $request);
    }

    $this->success('Ronda abierta: el sistema va a invitar a cada usuario a responder la encuesta.');
    $this->redirect('/usabilidad');
  }

  public function cerrar(Request $request): void
  {
    $this->verifyCsrf($request);

    $this->service->cerrarRonda();
    $this->success('Ronda cerrada.');
    $this->redirect('/usabilidad');
  }
}
