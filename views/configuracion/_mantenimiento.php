<?php
/** @var array<string, mixed> $valores */
$conOld = session()->hasOldInput();
?>
<p class="text-muted">
  Al finalizar una orden se puede cargar la fecha del próximo mantenimiento (limpieza interna, cambio de pasta
  térmica). Ese valor se usa para sugerirlo y para avisarle al cliente por email cuando se acerca la fecha.
</p>
<div class="row">
  <?= $view->partial('componentes/campo', [
    'nombre' => 'intervalo_meses', 'etiqueta' => 'Intervalo sugerido (meses)', 'tipo' => 'number', 'columna' => 'col-md-4 mb-3',
    'valor' => $valores['intervalo_meses'], 'atributos' => ['min' => 1, 'max' => 36],
  ]) ?>
</div>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="aviso_automatico" name="aviso_automatico" value="1"
    <?= ($conOld ? old('aviso_automatico') : $valores['aviso_automatico']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="aviso_automatico">Avisar automáticamente al cliente que se acerca su próximo mantenimiento</label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'aviso_dias_antes', 'etiqueta' => 'Días de anticipación', 'tipo' => 'number', 'columna' => 'mb-3', 'clase' => 'ancho-max-120',
  'valor' => $valores['aviso_dias_antes'], 'atributos' => ['min' => 0, 'max' => 60],
  'ayuda' => 'El aviso sale en horario de atención, como los recordatorios de turnos.',
]) ?>
