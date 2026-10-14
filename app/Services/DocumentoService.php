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
    private readonly FirmaService $firmas,
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
    (new Validator())
      ->check($items !== [], 'La orden todavía no tiene servicios ni repuestos cargados: editala y agregalos para armar el presupuesto.')
      ->validate();
    $config = $this->configuracion->obtener();
    $pagado = $this->pagos->totalPagado($ordenId);
    // La garantía corre desde que el cliente retira el equipo (si todavía no lo retiró, desde que se terminó).
    $finalizada = substr((string) ($orden['entregado_en'] ?? $orden['fecha_realizado'] ?? date('Y-m-d')), 0, 10);

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
      // Si hay repuestos que trajo el cliente, el documento aclara que la garantía no los cubre.
      'provistosCliente' => array_filter($items, fn($i) => (bool) $i['provisto_cliente']) !== [],
      'total' => array_sum(array_map(fn($i) => (float) $i['costo'], $items)),
      'negocio' => $config['negocio'],
      'trabajo' => $config['trabajo'],
      // La conformidad firmada en la pantalla al retirarlo (en el presupuesto queda la línea para firmar).
      'firma' => $entrega ? $this->firmas->dataUri($ordenId, 'entrega') : null,
      'firmaAclaracion' => $entrega ? ($this->firmas->deOrden($ordenId)['entrega']['aclaracion'] ?? null) : null,
      // QR: en el presupuesto, al link para aceptarlo; en la entrega, al seguimiento del equipo.
      'linkQr' => absolute_url('presupuesto/' . $this->ordenes->token($ordenId) . ($entrega ? '/seguimiento' : '')),
      'textoQr' => $entrega ? 'Escaneá para ver el trabajo y el próximo mantenimiento' : 'Escaneá para ver y aceptar el presupuesto',
    ];
  }

  public const TICKETS = ['recepcion' => 'Recepción', 'presupuesto' => 'Presupuesto', 'entrega' => 'Entrega'];

  /**
   * Ticket para impresora térmica (58 u 80 mm) con un QR: en la recepción y la entrega lleva
   * al seguimiento del equipo; en el presupuesto, al link para aceptarlo.
   *
   * @param string $tipo uno de TICKETS
   * @return array<string, mixed>
   */
  public function ticket(int $ordenId, string $tipo): array
  {
    $orden = $this->ordenService->obtener($ordenId);
    $items = $this->ordenes->items($ordenId);
    (new Validator())
      ->check(isset(self::TICKETS[$tipo]), 'Ese ticket no existe.')
      ->check($tipo !== 'presupuesto' || $items !== [], 'La orden todavía no tiene servicios ni repuestos: cargalos para imprimir el presupuesto.')
      ->check($tipo !== 'entrega' || $orden['estado'] === EstadoOrden::Finalizado->value, 'El ticket de entrega es para trabajos terminados.')
      ->validate();

    $config = $this->configuracion->obtener();
    $pagado = $this->pagos->totalPagado($ordenId);
    $token = $this->ordenes->token($ordenId);
    $link = absolute_url($tipo === 'presupuesto' ? "presupuesto/{$token}" : "presupuesto/{$token}/seguimiento");

    return [
      'tipo' => $tipo,
      'titulo' => self::TICKETS[$tipo],
      'orden' => $orden,
      'numero' => str_pad((string) $orden['id'], 4, '0', STR_PAD_LEFT),
      'cliente' => $this->clientes->find((int) $orden['cliente_id']),
      'items' => $tipo === 'recepcion' ? [] : $items,
      'total' => (float) $orden['total'],
      'pagado' => $pagado,
      'saldo' => round((float) $orden['total'] - $pagado, 2),
      'negocio' => $config['negocio'],
      'trabajo' => $config['trabajo'],
      'ancho' => (int) ($config['trabajo']['ticket_ancho'] ?? 80),
      'qr' => \App\Support\CodigoQr::svg($link),
      'textoQr' => $tipo === 'presupuesto' ? 'Escaneá para ver y aceptar el presupuesto' : 'Escaneá para seguir tu equipo',
    ];
  }

  /** @return array{nombre: string, contenido: string} */
  public function pdf(int $ordenId, bool $entrega = false): array
  {
    $datos = $this->datos($ordenId, $entrega);
    $datos['logo'] = $this->logoDataUri();
    $datos['qrPng'] = \App\Support\CodigoQr::pngDataUri($datos['linkQr']);

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
