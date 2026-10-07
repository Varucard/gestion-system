<?php
/**
 * @var array<string, mixed>|null $repuesto  repuesto en edición
 * @var list<array<string, mixed>> $repuestos
 * @var list<array<string, mixed>> $proveedores
 * @var float $margen  margen sugerido (%)
 */
?>
<div class="card mb-4 mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $repuesto ? 'Editar repuesto' : 'Nuevo repuesto' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($repuesto ? "repuestos/{$repuesto['id']}" : 'repuestos') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'codigo', 'etiqueta' => 'Código', 'valor' => $repuesto['codigo'] ?? '', 'columna' => 'col-md-3 mb-3',
          'clase' => 'text-uppercase', 'atributos' => ['maxlength' => 50],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'valor' => $repuesto['nombre'] ?? '', 'columna' => 'col-md-5 mb-3',
          'atributos' => ['minlength' => 2, 'maxlength' => 150, 'required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'precio_costo', 'etiqueta' => 'Costo', 'tipo' => 'number', 'prefijo' => '$', 'valor' => $repuesto['precio_costo'] ?? '',
          'columna' => 'col-md-2 mb-3', 'atributos' => ['step' => '0.01', 'min' => 0],
        ]) ?>
        <div class="col-md-2 mb-3">
          <?= $view->partial('componentes/campo', [
            'nombre' => 'precio', 'etiqueta' => 'Precio de venta *', 'tipo' => 'number', 'prefijo' => '$', 'valor' => $repuesto['precio'] ?? '',
            'atributos' => ['step' => '0.01', 'min' => 0, 'required' => true],
          ]) ?>
          <button type="button" class="btn btn-link btn-sm px-0" id="sugerir_precio" data-margen="<?= e((string) $margen) ?>">
            Sugerir con <?= qty($margen) ?>% de margen
          </button>
          <div class="small text-muted" id="margen_actual"></div>
        </div>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'proveedor_id', 'etiqueta' => 'Proveedor habitual', 'tipo' => 'select', 'buscable' => true, 'columna' => 'col-md-5 mb-3',
          'placeholder' => 'Sin proveedor', 'valor' => $repuesto['proveedor_id'] ?? '',
          'opciones' => array_column(array_map(fn($p) => [(int) $p['id'], $p['nombre']], $proveedores), 1, 0),
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'stock_minimo', 'etiqueta' => 'Stock mínimo', 'tipo' => 'number', 'columna' => 'col-md-3 mb-3',
          'valor' => isset($repuesto['stock_minimo']) ? (float) $repuesto['stock_minimo'] : '0',
          'atributos' => ['step' => '0.01', 'min' => 0], 'ayuda' => 'Avisa cuando el stock llega a este valor.',
        ]) ?>
        <?php if ($repuesto): ?>
          <div class="col-md-4 mb-3">
            <label class="form-label">Stock actual</label>
            <div class="input-group">
              <input type="text" class="form-control" value="<?= qty($repuesto['stock_actual']) ?>" readonly>
              <a href="<?= url("repuestos/{$repuesto['id']}/stock") ?>" class="btn btn-outline-secondary">Movimientos</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'columna' => 'mb-3',
        'valor' => $repuesto['descripcion'] ?? '', 'atributos' => ['rows' => 2],
      ]) ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $repuesto ? 'Actualizar repuesto' : 'Registrar repuesto' ?></button>
      <?php if ($repuesto): ?>
        <a href="<?= url('repuestos') ?>" class="btn btn-outline-secondary">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h4 class="mb-0">Repuestos registrados</h4>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-bordered js-datatable" data-order='[[1, "asc"]]' data-vacio="Todavía no hay repuestos cargados.">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Proveedor</th>
            <th>Costo</th>
            <th>Precio</th>
            <th>Margen</th>
            <th>Stock</th>
            <th data-orderable="false">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($repuestos as $row): ?>
            <?php $bajo = (float) $row['stock_minimo'] > 0 && (float) $row['stock_actual'] <= (float) $row['stock_minimo']; ?>
            <tr>
              <td><?= e($row['codigo'] ?? '') ?></td>
              <td>
                <?= e($row['nombre']) ?>
                <?php if ($row['descripcion']): ?><div class="small text-muted"><?= e($row['descripcion']) ?></div><?php endif; ?>
              </td>
              <td><?= e($row['proveedor'] ?? '—') ?></td>
              <?php $margenFila = (float) $row['precio_costo'] > 0 ? ((float) $row['precio'] / (float) $row['precio_costo'] - 1) * 100 : null; ?>
              <td data-order="<?= (float) $row['precio_costo'] ?>"><?= $row['precio_costo'] !== null ? importe($row['precio_costo']) : '—' ?></td>
              <td data-order="<?= (float) $row['precio'] ?>"><?= importe($row['precio']) ?></td>
              <td data-order="<?= $margenFila ?? -999 ?>" class="<?= $margenFila !== null && $margenFila < 0 ? 'text-danger' : '' ?>"><?= $margenFila !== null ? qty(round($margenFila, 1)) . ' %' : '—' ?></td>
              <td data-order="<?= (float) $row['stock_actual'] ?>">
                <span class="badge bg-<?= (float) $row['stock_actual'] < 0 || $bajo ? 'danger' : 'success' ?>"><?= qty($row['stock_actual']) ?></span>
                <?php if ($bajo): ?><small class="text-danger d-block">mín. <?= qty($row['stock_minimo']) ?></small><?php endif; ?>
              </td>
              <td class="col-acciones text-nowrap">
                <?= boton_accion("repuestos/{$row['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
                <?= boton_accion("repuestos/{$row['id']}/stock", 'box-seam', 'Stock') ?>
                <?= $view->partial('partials/delete_button', [
                  'action' => "repuestos/{$row['id']}/eliminar",
                  'label' => 'Eliminar',
                  'confirm' => "¿Eliminar el repuesto \"{$row['nombre']}\"?",
                ]) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // Precio sugerido = costo + margen, y margen actual mientras se escribe.
  document.addEventListener('DOMContentLoaded', () => {
    const costo = document.getElementById('precio_costo');
    const precio = document.getElementById('precio');
    const margen = document.getElementById('margen_actual');
    const mostrar = () => {
      const c = parseFloat(costo.value), p = parseFloat(precio.value);
      margen.textContent = c > 0 && p > 0 ? `Margen actual: ${((p / c - 1) * 100).toFixed(1).replace('.', ',')} %` : '';
    };
    document.getElementById('sugerir_precio').addEventListener('click', (e) => {
      const c = parseFloat(costo.value);
      if (c > 0) {
        precio.value = (c * (1 + parseFloat(e.target.dataset.margen) / 100)).toFixed(2);
        mostrar();
      } else {
        costo.focus();
      }
    });
    costo.addEventListener('input', mostrar);
    precio.addEventListener('input', mostrar);
    mostrar();
  });
</script>
