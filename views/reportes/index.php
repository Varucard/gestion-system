<?php
/**
 * @var string $desde
 * @var string $hasta
 * @var array<string, list<array<string, mixed>>> $datos
 * @var array<string, array<string, string>> $columnas
 */
$titulos = [
  'cobranzas' => 'Cobrado por mes y forma de pago',
  'servicios' => 'Servicios más realizados',
  'repuestos' => 'Repuestos más usados',
  'tecnicos' => 'Órdenes por técnico',
  'stock' => 'Stock valorizado (al día de hoy)',
];
$monetarias = ['total', 'precio_costo', 'precio', 'valor_costo', 'valor_venta'];
$query = http_build_query(['desde' => $desde, 'hasta' => $hasta]);
?>
<form method="GET" action="<?= url('reportes') ?>" class="row g-2 align-items-end mt-3">
  <div class="col-sm-3">
    <label for="desde" class="form-label">Desde</label>
    <input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>">
  </div>
  <div class="col-sm-3">
    <label for="hasta" class="form-label">Hasta</label>
    <input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>">
  </div>
  <div class="col-sm-2">
    <button type="submit" class="btn btn-primary w-100">Ver</button>
  </div>
  <div class="col-sm-4 small text-muted">Las órdenes se cuentan por su fecha de creación; los pagos, por su fecha de cobro.</div>
</form>

<?php foreach ($titulos as $clave => $titulo): ?>
  <?php $filas = $datos[$clave]; ?>
  <div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><?= e($titulo) ?></strong>
      <a href="<?= url("reportes/{$clave}/csv?{$query}") ?>" class="btn btn-sm btn-outline-success"><?= icono('download') ?> Excel (CSV)</a>
    </div>
    <div class="card-body table-responsive">
      <?php if ($filas === []): ?>
        <?= $view->partial('componentes/vacio', ['icono' => 'bar-chart', 'texto' => 'Sin datos para el período.']) ?>
      <?php else: ?>
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <?php foreach ($columnas[$clave] as $col => $etiqueta): ?>
                <th class="<?= $col === array_key_first($columnas[$clave]) || $col === 'forma_pago' || $col === 'nombre' ? '' : 'text-end' ?>"><?= e($etiqueta) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filas as $fila): ?>
              <tr>
                <?php foreach (array_keys($columnas[$clave]) as $col): ?>
                  <?php $valor = $fila[$col]; ?>
                  <?php if (in_array($col, $monetarias, true)): ?>
                    <td class="text-end"><?= $valor !== null ? importe($valor) : '—' ?></td>
                  <?php elseif (is_numeric($valor) && !in_array($col, ['codigo', 'mes'], true)): ?>
                    <td class="text-end"><?= qty($valor) ?></td>
                  <?php else: ?>
                    <td><?= e($col === 'mes' ? date('m/Y', strtotime($valor . '-01')) : ($valor ?? '—')) ?></td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <?php $totalCol = $clave === 'stock' ? 'valor_venta' : 'total'; ?>
          <tfoot>
            <tr>
              <th colspan="<?= count($columnas[$clave]) - 1 ?>" class="text-end">Total</th>
              <th class="text-end"><?= importe(array_sum(array_column($filas, $totalCol))) ?></th>
            </tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
