<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Repositories\PanelRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\TurnoRepository;
use App\Services\NotificacionService;

/** Panel de inicio con la actividad del día. */
final class HomeController extends Controller
{
  public function __construct(
    View $view,
    Session $session,
    private readonly PanelRepository $panel,
    private readonly TurnoRepository $turnos,
    private readonly OrdenRepository $ordenes,
    private readonly PagoRepository $pagos,
    private readonly RepuestoRepository $repuestos,
    private readonly NotificacionService $notificaciones,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $inicioMes = date('Y-m-01');
    $hoy = date('Y-m-d');
    $deudores = $this->pagos->deudores();

    $this->render('panel', [
      'title' => 'Inicio',
      'turnosHoy' => $this->turnos->delDia($hoy),
      'turnosManana' => $this->turnos->delDia(date('Y-m-d', strtotime('+1 day'))),
      'abiertas' => $this->panel->ordenesAbiertasPorEstado(),
      'ordenesAbiertas' => $this->ordenes->paginar(['estado' => 'abiertas', 'length' => 8, 'order' => [['column' => 0, 'dir' => 'desc']]])['filas'],
      'cobradoMes' => $this->panel->cobradoEntre($inicioMes, $hoy),
      'finalizadasMes' => $this->panel->ordenesFinalizadasEntre($inicioMes, $hoy),
      'deudores' => array_slice($deudores, 0, 5),
      'totalAdeudado' => array_sum(array_column($deudores, 'saldo')),
      'stockBajo' => $this->repuestos->bajoMinimo(),
      'mantenimientos' => $this->ordenes->mantenimientosProximos(30),
      'avisos' => [
        'canal' => $this->notificaciones->hayCanalDisponible(),
        'whatsapp' => $this->notificaciones->botonWhatsappManual(),
      ],
    ]);
  }
}
