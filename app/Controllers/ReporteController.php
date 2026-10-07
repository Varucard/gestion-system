<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Repositories\ReporteRepository;
use App\Support\Csv;
use App\Support\Validator;

/** Reportes de gestión con exportación a CSV (se abre con Excel). */
final class ReporteController extends Controller
{
  /** Encabezados de cada reporte exportable: columna => título. */
  private const COLUMNAS = [
    'cobranzas' => ['mes' => 'Mes', 'forma_pago' => 'Forma de pago', 'pagos' => 'Pagos', 'total' => 'Total'],
    'servicios' => ['nombre' => 'Servicio', 'ordenes' => 'Órdenes', 'cantidad' => 'Cantidad', 'total' => 'Total'],
    'repuestos' => ['nombre' => 'Repuesto', 'ordenes' => 'Órdenes', 'cantidad' => 'Cantidad', 'total' => 'Total'],
    'tecnicos' => ['tecnico' => 'Técnico', 'ordenes' => 'Órdenes', 'finalizadas' => 'Finalizadas', 'abiertas' => 'Abiertas', 'total' => 'Total'],
    'stock' => ['codigo' => 'Código', 'nombre' => 'Repuesto', 'stock_actual' => 'Stock', 'precio_costo' => 'Costo', 'precio' => 'Precio', 'valor_costo' => 'Valor a costo', 'valor_venta' => 'Valor a precio de venta'],
  ];

  public function __construct(View $view, Session $session, private readonly ReporteRepository $reportes)
  {
    parent::__construct($view, $session);
  }

  public function index(Request $request): void
  {
    [$desde, $hasta] = $this->periodo($request);

    $this->render('reportes/index', [
      'title' => 'Reportes',
      'desde' => $desde,
      'hasta' => $hasta,
      'datos' => array_combine(array_keys(self::COLUMNAS), array_map(fn($r) => $this->datos($r, $desde, $hasta), array_keys(self::COLUMNAS))),
      'columnas' => self::COLUMNAS,
    ]);
  }

  public function csv(Request $request, string $reporte): void
  {
    if (!isset(self::COLUMNAS[$reporte])) {
      throw new NotFoundException('Reporte inexistente.');
    }
    [$desde, $hasta] = $this->periodo($request);
    $columnas = self::COLUMNAS[$reporte];

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$reporte}_{$desde}_{$hasta}.csv\"");

    // BOM + punto y coma: Excel en español lo abre con acentos y columnas correctas.
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, array_values($columnas), ';', '"', '');
    foreach ($this->datos($reporte, $desde, $hasta) as $fila) {
      fputcsv($salida, array_map(
        fn(string $col) => Csv::celda($fila[$col], in_array($col, ['codigo', 'mes'], true)),
        array_keys($columnas)
      ), ';', '"', '');
    }
    fclose($salida);
  }

  /** @return list<array<string, mixed>> */
  private function datos(string $reporte, string $desde, string $hasta): array
  {
    return match ($reporte) {
      'cobranzas' => $this->reportes->cobranzas($desde, $hasta),
      'servicios' => $this->reportes->masVendidos('servicio', $desde, $hasta),
      'repuestos' => $this->reportes->masVendidos('repuesto', $desde, $hasta),
      'tecnicos' => $this->reportes->porTecnico($desde, $hasta),
      'stock' => $this->reportes->stockValorizado(),
    };
  }

  /** @return array{0: string, 1: string} período pedido o el mes en curso */
  private function periodo(Request $request): array
  {
    $desde = (string) $request->query('desde', '');
    $hasta = (string) $request->query('hasta', '');

    return [
      Validator::fecha($desde) ? $desde : date('Y-m-01'),
      Validator::fecha($hasta) ? $hasta : date('Y-m-d'),
    ];
  }
}
