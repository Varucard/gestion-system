<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Repositories\OrdenRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\StockRepository;
use App\Support\Validator;

/**
 * Movimientos de stock de repuestos.
 *
 * Regla: una orden descuenta sus repuestos al pasar a "finalizado" y los
 * repone si deja de estar finalizada. La bandera `stock_descontado` de la
 * orden evita descontar dos veces.
 */
final class StockService
{
  public function __construct(
    private readonly StockRepository $stock,
    private readonly RepuestoRepository $repuestos,
    private readonly ProveedorRepository $proveedores,
    private readonly OrdenRepository $ordenes,
    private readonly ConfiguracionService $configuracion,
    private readonly Auditor $auditor,
  ) {
  }

  /**
   * Registra una compra/ingreso. Si se indica el costo unitario, queda como
   * último costo del repuesto y, opcionalmente, se recalcula el precio de
   * venta con el margen sugerido.
   */
  public function ingresar(
    int $repuestoId,
    string $cantidad,
    ?int $proveedorId,
    ?string $motivo,
    ?int $usuarioId,
    string $costoUnitario = '',
    bool $actualizarPrecio = false,
  ): float {
    $repuesto = $this->repuesto($repuestoId);
    $valor = Validator::importe($cantidad);
    $costo = trim($costoUnitario) === '' ? null : Validator::importe($costoUnitario);

    (new Validator())
      ->check($valor !== null && $valor > 0, 'La cantidad a ingresar debe ser mayor a 0.')
      ->check(trim($costoUnitario) === '' || $costo !== null, 'El costo unitario no es válido.')
      ->check(!$actualizarPrecio || $costo !== null, 'Para actualizar el precio de venta hay que indicar el costo.')
      ->check($proveedorId === null || $this->proveedores->find($proveedorId) !== null, 'El proveedor seleccionado no existe.')
      ->validate();

    $resultante = $this->repuestos->transaction(function () use ($repuestoId, $valor, $proveedorId, $usuarioId, $motivo, $costo, $actualizarPrecio, $repuesto) {
      $resultante = $this->stock->registrar($repuestoId, 'ingreso', $valor, null, $proveedorId, $usuarioId, Validator::nullable((string) $motivo), $costo);

      if ($costo !== null) {
        $this->repuestos->setCosto($repuestoId, $costo);
      }
      if ($actualizarPrecio) {
        $nuevo = PrecioService::conMargen($costo, (float) $this->configuracion->seccion('stock')['margen_sugerido']);
        $this->repuestos->setPrecio($repuestoId, $nuevo);
        $this->auditor->registrar('cambiar_precio', 'repuesto', $repuestoId, "Precio de \"{$repuesto['nombre']}\" actualizado por costo: $ " . money($repuesto['precio']) . ' → $ ' . money($nuevo), ['antes' => (float) $repuesto['precio'], 'despues' => $nuevo, 'costo' => $costo]);
      }

      return $resultante;
    });
    $this->auditor->registrar('ingreso_stock', 'repuesto', $repuestoId, "Ingreso de stock: +" . qty($valor) . ' (queda ' . qty($resultante) . ')', ['cantidad' => $valor, 'proveedor_id' => $proveedorId, 'costo_unitario' => $costo]);

    return $resultante;
  }

  /** Corrige el stock al valor contado físicamente; registra la diferencia. */
  public function ajustar(int $repuestoId, string $stockReal, ?string $motivo, ?int $usuarioId): float
  {
    $repuesto = $this->repuesto($repuestoId);
    $valor = Validator::importe($stockReal);
    $motivo = Validator::nullable((string) $motivo);

    (new Validator())
      ->check($valor !== null, 'El stock real debe ser un número mayor o igual a 0.')
      ->check($motivo !== null, 'Indicá el motivo del ajuste.')
      ->validate();

    $diferencia = round($valor - (float) $repuesto['stock_actual'], 2);
    if ($diferencia == 0) {
      return $valor;
    }

    $resultante = $this->stock->registrar($repuestoId, 'ajuste', $diferencia, null, null, $usuarioId, $motivo);
    $this->auditor->registrar('ajuste_stock', 'repuesto', $repuestoId, "Ajuste de stock de \"{$repuesto['nombre']}\": " . qty($repuesto['stock_actual']) . ' → ' . qty($resultante) . " ({$motivo})", ['antes' => (float) $repuesto['stock_actual'], 'despues' => $resultante]);

    return $resultante;
  }

  public function descontarOrden(int $ordenId, ?int $usuarioId): void
  {
    if (!$this->configuracion->seccion('stock')['permitir_negativo']) {
      $faltantes = [];
      foreach ($this->ordenes->items($ordenId) as $item) {
        if ($item['repuesto_id'] === null) {
          continue;
        }
        $repuesto = $this->repuesto((int) $item['repuesto_id']);
        if ((float) $repuesto['stock_actual'] < (float) $item['cantidad']) {
          $faltantes[] = sprintf('%s (hay %s, se necesitan %s)', $repuesto['nombre'], qty($repuesto['stock_actual']), qty($item['cantidad']));
        }
      }
      (new Validator())->check($faltantes === [], 'No hay stock suficiente para finalizar la orden: ' . implode('; ', $faltantes) . '.')->validate();
    }

    $this->moverOrden($ordenId, -1, $usuarioId, "Orden #{$ordenId} finalizada");
  }

  public function reponerOrden(int $ordenId, ?int $usuarioId): void
  {
    $this->moverOrden($ordenId, 1, $usuarioId, "Orden #{$ordenId} reabierta o cancelada");
  }

  private function moverOrden(int $ordenId, int $signo, ?int $usuarioId, string $motivo): void
  {
    $this->ordenes->transaction(function () use ($ordenId, $signo, $usuarioId, $motivo) {
      foreach ($this->ordenes->items($ordenId) as $item) {
        if ($item['repuesto_id'] !== null) {
          $this->stock->registrar(
            (int) $item['repuesto_id'],
            $signo < 0 ? 'egreso' : 'ingreso',
            $signo * (float) $item['cantidad'],
            $ordenId,
            null,
            $usuarioId,
            $motivo,
          );
        }
      }
      $this->ordenes->setStockDescontado($ordenId, $signo < 0);
    });
  }

  /** @return array<string, mixed> */
  private function repuesto(int $id): array
  {
    return $this->repuestos->find($id) ?? throw new NotFoundException('Repuesto no encontrado.');
  }
}
