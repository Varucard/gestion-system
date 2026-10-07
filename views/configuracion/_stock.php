<?php /** @var array<string, mixed> $valores */ ?>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="permitir_negativo" name="permitir_negativo" value="1"
    <?= (session()->hasOldInput() ? old('permitir_negativo') : $valores['permitir_negativo']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="permitir_negativo">Permitir finalizar órdenes aunque el stock de un repuesto quede negativo</label>
</div>
<p class="text-muted small">
  Si se desactiva, una orden no se puede pasar a <em>Finalizada</em> hasta registrar el ingreso de los repuestos que faltan.
</p>
<?= $view->partial('componentes/campo', [
  'nombre' => 'margen_sugerido', 'etiqueta' => 'Margen sugerido sobre el costo (%)', 'tipo' => 'number', 'columna' => 'mb-3',
  'clase' => 'ancho-max-140', 'valor' => $valores['margen_sugerido'], 'atributos' => ['min' => 0, 'max' => 1000, 'step' => '0.5'],
  'ayuda' => 'Se usa para sugerir el precio de venta al cargar el costo de un repuesto.',
]) ?>
