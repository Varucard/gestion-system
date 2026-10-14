<?php
/**
 * @var array<string, mixed> $repuesto
 * @var list<array<string, mixed>> $movimientos
 * @var list<array<string, mixed>> $proveedores
 * @var float $margen
 */
$tipos = ['ingreso' => ['Ingreso', 'success'], 'egreso' => ['Egreso', 'warning'], 'ajuste' => ['Ajuste', 'secondary']];
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0"><?= e($repuesto['nombre']) ?> <?= $repuesto['codigo'] ? '<small class="text-muted">(' . e($repuesto['codigo']) . ')</small>' : '' ?></h4>
    <span class="fs-5">Stock actual: <strong><?= qty($repuesto['stock_actual']) ?></strong>
      <?php if ((float) $repuesto['stock_minimo'] > 0): ?><small class="text-muted">· mínimo <?= qty($repuesto['stock_minimo']) ?></small><?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-lg-6">
        <h5>Registrar ingreso</h5>
        <form action="<?= url("repuestos/{$repuesto['id']}/ingresos") ?>" method="POST">
          <?= csrf_field() ?>
          <div class="row">
            <?= $view->partial('componentes/campo', [
              'nombre' => 'cantidad', 'etiqueta' => 'Cantidad *', 'tipo' => 'importe', 'columna' => 'col-sm-4 mb-2', 'usarAnterior' => false,
              'atributos' => ['data-min' => '0.01', 'data-formato' => 'cantidad', 'required' => true, 'data-error-min' => 'La cantidad tiene que ser mayor a 0.'],
            ]) ?>
            <?= $view->partial('componentes/campo', [
              'nombre' => 'proveedor_id', 'etiqueta' => 'Proveedor', 'tipo' => 'select', 'columna' => 'col-sm-8 mb-2', 'usarAnterior' => false,
              'valor' => $repuesto['proveedor_id'] ?? '',
              'opciones' => ['' => '—'] + array_column(array_map(fn($p) => [(int) $p['id'], $p['nombre']], $proveedores), 1, 0),
            ]) ?>
          </div>
          <div class="row">
            <?= $view->partial('componentes/campo', [
              'nombre' => 'costo_unitario', 'etiqueta' => 'Costo unitario', 'tipo' => 'importe', 'prefijo' => '$', 'columna' => 'col-sm-4 mb-2', 'usarAnterior' => false,
              'atributos' => ['data-min' => 0],
              'ayuda' => $repuesto['precio_costo'] !== null ? 'Último costo: $ ' . money($repuesto['precio_costo']) : 'Si lo dejás vacío, no cambia el costo.',
            ]) ?>
            <div class="col-sm-8 mb-2 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="actualizar_precio" name="actualizar_precio" value="1">
                <label class="form-check-label small" for="actualizar_precio">
                  Actualizar el precio de venta con <?= qty($margen) ?>% de margen (hoy <?= importe($repuesto['precio']) ?>)
                </label>
              </div>
            </div>
          </div>
          <?= $view->partial('componentes/campo', [
            'nombre' => 'motivo', 'id' => 'motivo_ingreso', 'etiqueta' => 'Comprobante / observación', 'columna' => 'mb-2', 'usarAnterior' => false,
            'atributos' => ['maxlength' => 255, 'placeholder' => 'Ej: Factura A 0001-00001234'],
          ]) ?>
          <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> Registrar ingreso</button>
        </form>
      </div>
      <div class="col-lg-6">
        <?php // Plegado y explicado: cargar acá lo que llegó pisaría el stock en vez de sumarlo. ?>
        <details class="mas-datos">
          <summary>¿El stock no coincide con lo que hay? Ajustar por conteo</summary>
          <p class="small text-muted mt-2 mb-3">
            Usalo después de contar los repuestos en el estante: <strong>reemplaza</strong> el stock actual
            (<?= qty($repuesto['stock_actual']) ?>) por el que contaste. Para sumar lo que llegó de un proveedor, usá «Registrar ingreso».
          </p>
        <form action="<?= url("repuestos/{$repuesto['id']}/ajustes") ?>" method="POST"
          data-confirm="¿Reemplazar el stock actual (<?= e(qty($repuesto['stock_actual'])) ?>) por el que contaste?" data-confirm-aceptar="Sí, reemplazarlo">
          <?= csrf_field() ?>
          <div class="row">
            <?= $view->partial('componentes/campo', [
              'nombre' => 'stock_real', 'etiqueta' => 'Stock que contaste *', 'tipo' => 'importe', 'columna' => 'col-sm-4 mb-2', 'usarAnterior' => false,
              'atributos' => ['data-min' => 0, 'data-formato' => 'cantidad', 'required' => true],
            ]) ?>
            <?= $view->partial('componentes/campo', [
              'nombre' => 'motivo', 'id' => 'motivo_ajuste', 'etiqueta' => 'Motivo *', 'columna' => 'col-sm-8 mb-2', 'usarAnterior' => false,
              'atributos' => ['maxlength' => 255, 'required' => true, 'placeholder' => 'Ej: Inventario mensual'],
            ]) ?>
          </div>
          <button type="submit" class="btn btn-outline-secondary"><?= icono('check-lg') ?> Ajustar stock</button>
        </form>
        </details>
      </div>
    </div>

    <h5 class="mt-4">Movimientos</h5>
    <div class="table-responsive">
      <table class="table table-striped table-bordered js-datatable" data-order='[[0, "desc"]]' data-vacio="Este repuesto todavía no tiene movimientos.">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Cantidad</th>
            <th>Costo unit.</th>
            <th>Stock resultante</th>
            <th>Detalle</th>
            <th>Usuario</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
            <?php [$etiqueta, $color] = $tipos[$m['tipo']]; ?>
            <tr>
              <td data-order="<?= (int) $m['id'] ?>"><?= format_date($m['created_at'], 'd/m/Y H:i') ?></td>
              <td><span class="badge bg-<?= $color ?>"><?= $etiqueta ?></span></td>
              <td class="<?= (float) $m['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>"><?= ((float) $m['cantidad'] > 0 ? '+' : '') . qty($m['cantidad']) ?></td>
              <td><?= $m['costo_unitario'] !== null ? importe($m['costo_unitario']) : '' ?></td>
              <td><?= qty($m['stock_resultante']) ?></td>
              <td>
                <?= e($m['motivo'] ?? '') ?>
                <?php if ($m['proveedor']): ?><div class="small text-muted">Proveedor: <?= e($m['proveedor']) ?></div><?php endif; ?>
                <?php if ($m['orden_id']): ?><a class="small" href="<?= url("ordenes/{$m['orden_id']}/presupuesto") ?>">Ver orden #<?= (int) $m['orden_id'] ?></a><?php endif; ?>
              </td>
              <td><?= e($m['usuario'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a href="<?= url('repuestos') ?>" class="btn btn-outline-secondary mt-2">Volver a repuestos</a>
  </div>
</div>
