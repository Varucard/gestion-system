<?php
/**
 * @var array<string, mixed>|null $combo
 * @var array{servicio: array<int, array<string, mixed>>, repuesto: array<int, array<string, mixed>>} $detalle
 * @var list<array<string, mixed>> $servicios
 * @var list<array<string, mixed>> $repuestos
 */
if (session()->hasOldInput()) {
  foreach (['servicio', 'repuesto'] as $tipo) {
    $detalle[$tipo] = [];
    $cantidades = (array) old("cantidad_{$tipo}", []);
    foreach ((array) old("{$tipo}_id", []) as $itemId) {
      $detalle[$tipo][(int) $itemId] = ['cantidad' => $cantidades[$itemId] ?? 1];
    }
  }
}
$activo = $combo === null || (session()->hasOldInput() ? old('activo') : $combo['activo']);
$view->script('ordenes.js');
?>
<div class="card mt-3">
  <div class="card-header"><h4 class="mb-0"><?= $combo ? 'Editar combo' : 'Nuevo combo' ?></h4></div>
  <div class="card-body">
    <form action="<?= url($combo ? "combos/{$combo['id']}" : 'combos') ?>" method="POST" id="form_orden" data-sin-precio="1"
      data-detalle="<?= e(json_encode($detalle, JSON_FORCE_OBJECT)) ?>">
      <?= csrf_field() ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'valor' => $combo['nombre'] ?? '', 'columna' => 'col-md-5 mb-3',
          'atributos' => ['maxlength' => 100, 'required' => true, 'placeholder' => 'Ej: Mantenimiento completo'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'valor' => $combo['descripcion'] ?? '', 'columna' => 'col-md-7 mb-3',
          'atributos' => ['maxlength' => 255],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'servicio_id[]', 'id' => 'servicio_id', 'etiqueta' => 'Servicios', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6 mb-3', 'clase' => 'js-item-precio', 'placeholder' => 'Elegí servicios',
          'atributos' => ['multiple' => true, 'data-tipo' => 'servicio'],
          'valor' => array_keys($detalle['servicio']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($s) => [(int) $s['id'], [
            'texto' => $s['nombre'], 'atributos' => ['data-precio' => (float) $s['precio_base']],
          ]], $servicios), 1, 0),
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'repuesto_id[]', 'id' => 'repuesto_id', 'etiqueta' => 'Repuestos', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6 mb-3', 'clase' => 'js-item-precio', 'placeholder' => 'Elegí repuestos',
          'atributos' => ['multiple' => true, 'data-tipo' => 'repuesto'],
          'valor' => array_keys($detalle['repuesto']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($r) => [(int) $r['id'], [
            'texto' => $r['nombre'], 'atributos' => ['data-precio' => (float) $r['precio']],
          ]], $repuestos), 1, 0),
        ]) ?>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle" id="detalle_orden">
          <thead><tr><th>Ítem</th><th class="ancho-140">Cantidad</th><th class="text-end ancho-160">Subtotal actual</th></tr></thead>
          <tbody><tr class="js-sin-items"><td colspan="3" class="text-muted">Elegí servicios y repuestos.</td></tr></tbody>
        </table>
      </div>
      <p><strong>Total a precios actuales: $ <span id="total_combo">0,00</span></strong></p>

      <?php if ($combo): ?>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
          <label class="form-check-label" for="activo">Combo activo (aparece en las órdenes)</label>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $combo ? 'Guardar cambios' : 'Crear combo' ?></button>
      <a href="<?= url('combos') ?>" class="btn btn-outline-secondary">Volver</a>
    </form>
  </div>
</div>
