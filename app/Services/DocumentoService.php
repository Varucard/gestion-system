<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Enums\EstadoOrden;
use App\Repositories\ClienteRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\PagoRepository;
use App\Support\Validator;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Documentos imprimibles de una orden: presupuesto y comprobante de entrega
 * (con conformidad del cliente), en HTML y PDF.
 */
final class DocumentoService
{
  public function __construct(
    private readonly OrdenService $ordenService,
    private readonly OrdenRepository $ordenes,
    private readonly ClienteRepository $clientes,
    private readonly PagoRepository $pagos,
    private readonly ConfiguracionService $configuracion,
    private readonly View $view,
  ) {
  }

  /**
   * Variables para las vistas del documento.
   *
   * @param bool $entrega true: comprobante de entrega (solo órdenes finalizadas)
   * @return array<string, mixed>
   */
  public function datos(int $ordenId, bool $entrega = false): array
  {
    $orden = $this->ordenService->obtener($ordenId);
    (new Validator())
      ->check(!$entrega || $orden['estado'] === EstadoOrden::Finalizado->value, 'El comprobante de entrega solo está disponible para órdenes finalizadas.')
      ->validate();

    $items = $this->ordenes->items($ordenId);
    $config = $this->configuracion->obtener();
    $pagado = $this->pagos->totalPagado($ordenId);
    $finalizada = $orden['fecha_realizado'] ?? date('Y-m-d');

    return [
      'entrega' => $entrega,
      'titulo' => $entrega ? 'Comprobante de entrega' : 'Presupuesto de Reparación',
      'pagado' => $pagado,
      'saldo' => round((float) $orden['total'] - $pagado, 2),
      'vencimientoGarantia' => date('d/m/Y', strtotime("{$finalizada} +{$config['trabajo']['garantia']} days")),
      'orden' => $orden,
      'numero' => str_pad((string) $orden['id'], 4, '0', STR_PAD_LEFT),
      'cliente' => $this->clientes->find((int) $orden['cliente_id']),
      'items' => $items,
      'total' => array_sum(array_map(fn($i) => (float) $i['costo'], $items)),
      'negocio' => $config['negocio'],
      'trabajo' => $config['trabajo'],
    ];
  }

  /** @return array{nombre: string, contenido: string} */
  public function pdf(int $ordenId, bool $entrega = false): array
  {
    $datos = $this->datos($ordenId, $entrega);
    $datos['logo'] = $this->logoDataUri();

    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setDefaultFont('DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($this->view->render('presupuestos/pdf', $datos, null), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return [
      'nombre' => ($entrega ? 'Entrega' : 'Presupuesto') . "_{$datos['numero']}.pdf",
      'contenido' => (string) $dompdf->output(),
    ];
  }

  /** Dompdf no carga recursos remotos: el logo se incrusta como data URI. */
  private function logoDataUri(): string
  {
    $file = dirname(__DIR__, 2) . '/public/assets/img/logo.png';

    return is_file($file) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($file)) : '';
  }
}
