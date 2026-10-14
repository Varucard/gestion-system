<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\PrimerosPasosService;

/** Primeros pasos para dejar el sistema listo. Solo administradores. */
final class PrimerosPasosController extends Controller
{
  public function __construct(View $view, Session $session, private readonly PrimerosPasosService $service)
  {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->render('primeros_pasos/index', ['title' => 'Primeros pasos', 'pasos' => $this->service->pasos()]);
  }

  public function cargarMarcas(Request $request): void
  {
    $this->verifyCsrf($request);

    $agregados = $this->service->cargarMarcasComunes();
    $agregados > 0
      ? $this->success("Se cargaron las marcas comunes con {$agregados} modelos. Si falta alguno, se agrega escribiéndolo al cargar el equipo.")
      : $this->success('Las marcas comunes ya estaban cargadas.');
    $this->redirect('/primeros-pasos');
  }

  public function ocultar(Request $request): void
  {
    $this->verifyCsrf($request);

    $this->service->ocultar();
    $this->success('Listo, ya no se muestra en el inicio. Los primeros pasos siguen en Configuración.');
    $this->redirect('/');
  }
}
