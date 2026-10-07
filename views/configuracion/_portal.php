<?php
/** @var array<string, mixed> $valores */
$conOld = session()->hasOldInput();
$check = fn(string $k) => ($conOld ? old($k) : $valores[$k]) ? 'checked' : '';
?>
<p>
  Página pública para que los clientes consulten el estado de sus trabajos y sus próximos turnos:
  <a href="<?= url('seguimiento') ?>" target="_blank" rel="noopener"><?= e(absolute_url('seguimiento')) ?></a>
</p>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="habilitado" name="habilitado" value="1" <?= $check('habilitado') ?>>
  <label class="form-check-label" for="habilitado">Portal habilitado</label>
</div>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="requiere_orden" name="requiere_orden" value="1" <?= $check('requiere_orden') ?>>
  <label class="form-check-label" for="requiere_orden">Pedir el número de una orden además del DNI <strong>(recomendado)</strong></label>
  <div class="form-text">Con solo el DNI, cualquiera que conozca el DNI de otra persona podría ver sus trabajos. El número de orden figura en el comprobante de ingreso y en el presupuesto.</div>
</div>
<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" role="switch" id="mostrar_montos" name="mostrar_montos" value="1" <?= $check('mostrar_montos') ?>>
  <label class="form-check-label" for="mostrar_montos">Mostrar totales y saldos</label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'cantidad_ordenes', 'etiqueta' => 'Cantidad de trabajos a mostrar', 'tipo' => 'number', 'columna' => 'mb-3',
  'clase' => 'ancho-max-120', 'valor' => $valores['cantidad_ordenes'], 'atributos' => ['min' => 1, 'max' => 50],
]) ?>
