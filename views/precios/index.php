<?php
/**
 * @var list<array<string, mixed>> $proveedores
 * @var array{parametros: array<string, mixed>, items: list<array<string, mixed>>}|null $vista
 */
use App\Services\PrecioService;

$p = $vista['parametros'] ?? [];
$valor = fn(string $k, mixed $def = '') => old($k, $p[$k] ?? $def);
?>
<div class="card mt-3">
  <div class="card-header"><h4 class="mb-0">Actualización masiva de precios</h4></div>
  <div class="card-body">
    <form action="<?= url('precios/vista-previa') ?>" method="POST" class="row g-3 align-items-end">
      <?= csrf_field() ?>
      <div class="col-md-3">
        <label for="aplicar_a" class="form-label">Aplicar a</label>
        <select class="form-select" id="aplicar_a" name="aplicar_a">
          <?php foreach (PrecioService::APLICAR_A as $clave => $etiqueta): ?>
            <option value="<?= e($clave) ?>" <?= selected($clave === $valor('aplicar_a', 'ambos')) ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label for="proveedor_id" class="form-label">Solo repuestos del proveedor</label>
        <select class="form-select" id="proveedor_id" name="proveedor_id">
          <option value="">Todos</option>
          <?php foreach ($proveedores as $prov): ?>
            <option value="<?= (int) $prov['id'] ?>" <?= selected((int) $prov['id'] === (int) $valor('proveedor_id', 0)) ?>><?= e($prov['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label for="porcentaje" class="form-label">Porcentaje</label>
        <div class="input-group">
          <input type="number" class="form-control" id="porcentaje" name="porcentaje" step="0.01" min="-90" max="500" required
            value="<?= e((string) $valor('porcentaje')) ?>">
          <span class="input-group-text">%</span>
        </div>
      </div>
      <div class="col-md-2">
        <label for="redondeo" class="form-label">Redondear</label>
        <select class="form-select" id="redondeo" name="redondeo">
          <?php foreach (PrecioService::REDONDEOS as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= selected($clave === (int) $valor('redondeo', 0)) ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Ver cambios</button>
      </div>
      <div class="col-12"><small class="text-muted">Un porcentaje negativo baja los precios. El redondeo es hacia arriba en un aumento y hacia abajo en una rebaja. Nada se guarda hasta confirmar.</small></div>
    </form>
  </div>
</div>

<?php if ($vista !== null): ?>
  <div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Vista previa: <?= count($vista['items']) ?> precios</strong>
      <span class="small text-muted">Destildá los que no quieras modificar.</span>
    </div>
    <div class="card-body">
      <form action="<?= url('precios/aplicar') ?>" method="POST" data-confirm="¿Aplicar los nuevos precios seleccionados?">
        <?= csrf_field() ?>
        <?php foreach (['aplicar_a', 'porcentaje', 'redondeo', 'proveedor_id'] as $campo): ?>
          <input type="hidden" name="<?= $campo ?>" value="<?= e((string) ($p[$campo] ?? '')) ?>">
        <?php endforeach; ?>
        <div class="table-responsive alto-max-60vh">
          <table class="table table-sm align-middle">
            <thead class="sticky-top bg-body">
              <tr>
                <th><input type="checkbox" class="form-check-input" checked aria-label="Seleccionar todos"
                  onclick="document.querySelectorAll('.js-precio-item').forEach(c => c.checked = this.checked)"></th>
                <th>Tipo</th><th>Nombre</th><th class="text-end">Actual</th><th class="text-end">Nuevo</th><th class="text-end">Diferencia</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vista['items'] as $item): ?>
                <tr>
                  <td>
                    <input type="checkbox" class="form-check-input js-precio-item" name="ids[]" value="<?= e("{$item['tipo']}:{$item['id']}") ?>" checked aria-label="Incluir <?= e($item['nombre']) ?>">
                    <input type="hidden" name="actual[<?= e("{$item['tipo']}:{$item['id']}") ?>]" value="<?= e(number_format($item['actual'], 2, '.', '')) ?>">
                  </td>
                  <td><?= $item['tipo'] === 'servicio' ? 'Servicio' : 'Repuesto' ?></td>
                  <td><?= e($item['nombre']) ?></td>
                  <td class="text-end"><?= importe($item['actual']) ?></td>
                  <td class="text-end fw-semibold"><?= importe($item['nuevo']) ?></td>
                  <td class="text-end <?= $item['nuevo'] >= $item['actual'] ? 'text-success' : 'text-danger' ?>"><?= importe($item['nuevo'] - $item['actual']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-seccion mt-2" <?= $vista['items'] === [] ? 'disabled' : '' ?>><?= icono('check-lg') ?> Aplicar precios</button>
      </form>
    </div>
  </div>
<?php endif; ?>
