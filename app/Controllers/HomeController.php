<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrdenRepository;
use App\Repositories\PanelRepository;

/** Panel de inicio: accesos de todos los días y los números del negocio. */
final class HomeController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly PanelRepository $panel,
    private readonly OrdenRepository $ordenes,
    private readonly \App\Services\ConfiguracionService $configuracion,
    private readonly \App\Services\NotificacionService $notificaciones,
    private readonly \App\Services\PrimerosPasosService $primerosPasos,
    private readonly \App\Core\Auth $auth,
  ) {
    parent::__construct($view, $session);
  }

  /** JSON: la sesión sigue abierta (ver el modo mostrador en app.js). */
  public function sesion(Request $request): void
  {
    $this->json(['status' => 'ok']);
  }

  /** @return list<array<string, mixed>>|null los primeros pasos, si el administrador todavía los tiene que ver */
  private function primerosPasosDelInicio(): ?array
  {
    if (!$this->auth->esAdministrador() || $this->primerosPasos->ocultos()) {
      return null;
    }
    $pasos = $this->primerosPasos->pasos();

    return $this->primerosPasos->mostrarEnElInicio($pasos) ? $pasos : null;
  }

  public function index(Request $request): void
  {
    $inicioMes = date('Y-m-01');
    $hoy = date('Y-m-d');
    $deuda = $this->panel->deuda();
    $trabajo = $this->configuracion->seccion('trabajo');

    // El inicio muestra números; cada uno lleva a su listado con el detalle. Se cuentan en la
    // base (COUNT/SUM), sin traer las listas enteras.
    $this->render('panel', [
      'title' => 'Inicio',
      'turnosHoy' => $this->panel->turnosDelDia($hoy),
      'abiertas' => $this->panel->ordenesAbiertasPorEstado(),
      'cobradoMes' => $this->panel->cobradoEntre($inicioMes, $hoy),
      'pagosMes' => $this->panel->pagosEntre($inicioMes, $hoy),
      'deudores' => $deuda['clientes'],
      'totalAdeudado' => $deuda['total'],
      'stockBajo' => $this->panel->repuestosBajoMinimo(),
      'mantenimientos' => $this->ordenes->mantenimientosProximos(30),
      // "Para no olvidarse": lo que se pasa y cuesta plata.
      'sinRetirar' => $this->panel->listosSinRetirar((int) $trabajo['alerta_listo_dias']),
      'sinRespuesta' => $this->panel->presupuestosSinRespuesta((int) $trabajo['alerta_presupuesto_dias']),
      'whatsapp' => $this->notificaciones->botonWhatsappManual(),
      'primerosPasos' => $this->primerosPasosDelInicio(),
    ]);
  }
}
