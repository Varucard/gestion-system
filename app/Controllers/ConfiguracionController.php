<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\NotificacionRepository;
use App\Services\ConfiguracionService;
use App\Services\NotificacionService;

final class ConfiguracionController extends Controller
{
  public const PESTANAS = [
    'negocio' => 'Negocio',
    'trabajo' => 'Presupuestos',
    'turnos' => 'Turnos y horario',
    'mantenimiento' => 'Mantenimiento preventivo',
    'notificaciones' => 'Avisos',
    'mensajes' => 'Mensajes',
    'stock' => 'Stock',
    'portal' => 'Portal de clientes',
    'backups' => 'Backups',
  ];

  public function __construct(
    View $view,
    Session $session,
    private readonly ConfiguracionService $service,
    private readonly NotificacionService $notificaciones,
    private readonly NotificacionRepository $registro,
    private readonly \App\Services\Auditor $auditor,
    private readonly \App\Services\BackupService $backups,
    private readonly \App\Core\Logger $logger,
  ) {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    $this->redirect('/configuracion/negocio');
  }

  public function edit(Request $request, string $seccion): void
  {
    if (!isset(self::PESTANAS[$seccion])) {
      throw new NotFoundException('Sección de configuración inexistente.');
    }

    $this->render('configuracion/form', [
      'title' => 'Configuración del sistema',
      'seccion' => $seccion,
      'pestanas' => self::PESTANAS,
      'config' => $this->service->obtener(),
      'extra' => match ($seccion) {
        'notificaciones' => [
          'canales' => array_map(fn(string $c) => $this->notificaciones->canal($c), NotificacionService::CANALES),
          'registro' => $this->registro->recientes(50),
        ],
        'turnos' => ['horario' => $this->service->horario()],
        'backups' => ['archivos' => $this->backups->listar()],
        default => [],
      },
    ]);
  }

  public function update(Request $request, string $seccion): void
  {
    $this->verifyCsrf($request);

    try {
      $this->service->guardar($seccion, $request->all());
    } catch (ValidationException $e) {
      $this->backWithErrors("/configuracion/{$seccion}", $e, $request);
    }

    $this->auditor->registrar('configurar', 'configuracion', null, 'Configuración modificada: ' . (self::PESTANAS[$seccion] ?? $seccion));
    $this->success('Configuración guardada.');
    $this->redirect("/configuracion/{$seccion}");
  }

  public function generarBackup(Request $request): void
  {
    $this->verifyCsrf($request);

    try {
      $creados = $this->backups->generar(manual: true);
      $this->success('Backup generado: ' . implode(', ', $creados) . '.');
    } catch (\Throwable $e) {
      $this->logger->error('Falló el backup manual', ['exception' => $e]);
      $this->error('No se pudo generar el backup. Revisá el registro del sistema.');
    }

    $this->redirect('/configuracion/backups');
  }

  public function descargarBackup(Request $request): void
  {
    $ruta = $this->backups->ruta((string) $request->query('archivo', '')) ?? throw new NotFoundException('Backup inexistente.');
    $this->auditor->registrar('descargar_backup', 'sistema', null, 'Descarga del backup ' . basename($ruta));

    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
  }

  public function importarFeriados(Request $request): void
  {
    $this->verifyCsrf($request);
    $anio = (int) ($request->int('anio') ?: date('Y'));

    try {
      $nuevos = $this->service->importarFeriados($anio);
      $this->auditor->registrar('configurar', 'configuracion', null, "Se importaron {$nuevos} feriados de {$anio}");
      $this->success($nuevos > 0 ? "Se agregaron {$nuevos} feriados de {$anio}." : "Los feriados de {$anio} ya estaban cargados.");
    } catch (ValidationException $e) {
      $this->error($e->getMessage());
    }

    $this->redirect('/configuracion/turnos');
  }
}
