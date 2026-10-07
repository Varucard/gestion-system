<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioRepository;

/** Visores de auditoría (acciones del negocio) y del registro técnico (logs). */
final class RegistroController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly Logger $logger,
    private readonly AuditoriaRepository $auditoria,
    private readonly UsuarioRepository $usuarios,
  ) {
    parent::__construct($view, $session);
  }

  public function auditoria(Request $request): void
  {
    $this->render('registro/auditoria', [
      'title' => 'Auditoría',
      'entidades' => $this->auditoria->entidades(),
      'usuarios' => $this->usuarios->all(),
    ]);
  }

  public function auditoriaDatos(Request $request): void
  {
    $this->tabla($this->auditoria->paginar($request->queryAll()), 'registro/_fila_auditoria', 'a');
  }

  public function logs(Request $request): void
  {
    $fechas = $this->logger->fechas();
    $fecha = (string) $request->query('fecha', $fechas[0] ?? date('Y-m-d'));
    $nivel = (string) $request->query('nivel', 'info');
    $buscar = trim((string) $request->query('buscar', ''));

    $this->render('registro/logs', [
      'title' => 'Registro del sistema',
      'fechas' => $fechas,
      'fecha' => $fecha,
      'nivel' => array_key_exists($nivel, Logger::NIVELES) ? $nivel : 'info',
      'buscar' => $buscar,
      'eventos' => $this->logger->leer($fecha, $nivel, $buscar),
    ]);
  }
}
