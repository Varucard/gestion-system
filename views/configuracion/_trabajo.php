<?php /** @var array<string, mixed> $valores */ ?>
<div class="row">
  <?php foreach (['validez' => 'Validez del presupuesto', 'garantia' => 'Garantía', 'tiempo_estimado' => 'Tiempo estimado'] as $campo => $etiqueta): ?>
    <?= $view->partial('componentes/campo', [
      'nombre' => $campo, 'etiqueta' => "{$etiqueta} (días) *", 'tipo' => 'number', 'valor' => $valores[$campo], 'columna' => 'col-md-4 mb-3',
      'atributos' => ['min' => 1, 'max' => 365, 'required' => true],
    ]) ?>
  <?php endforeach; ?>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'forma_pago', 'etiqueta' => 'Formas de pago (una por línea) *', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => implode("\n", $valores['forma_pago']), 'atributos' => ['rows' => 5, 'required' => true],
]) ?>
<?= $view->partial('componentes/campo', [
  'nombre' => 'observaciones', 'etiqueta' => 'Observaciones del presupuesto (una por línea)', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => implode("\n", $valores['observaciones']), 'atributos' => ['rows' => 4],
]) ?>
<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" role="switch" id="aceptar_inicia_trabajo" name="aceptar_inicia_trabajo" value="1"
    <?= (session()->hasOldInput() ? old('aceptar_inicia_trabajo') : $valores['aceptar_inicia_trabajo']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="aceptar_inicia_trabajo">Cuando el cliente acepta el presupuesto desde el link, pasar la orden a <em>En proceso</em></label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'mensaje_legal', 'etiqueta' => 'Mensaje legal', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => $valores['mensaje_legal'], 'atributos' => ['rows' => 2],
]) ?>
