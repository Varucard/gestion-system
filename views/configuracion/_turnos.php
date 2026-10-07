<?php
/**
 * @var array<string, mixed> $valores
 * @var \App\Support\HorarioAtencion $horario
 */
use App\Support\HorarioAtencion;

$conOld = session()->hasOldInput();
$check = fn(string $k) => ($conOld ? old($k) : $valores[$k]) ? 'checked' : '';
?>
<h5>Horario de atención</h5>
<p class="text-muted small">Actual: <?= e($horario->resumen() ?: 'sin días de atención') ?></p>
<div class="table-responsive">
  <table class="table table-sm align-middle ancho-max-560">
    <thead><tr><th>Día</th><th>Atiende</th><th>Desde</th><th>Hasta</th></tr></thead>
    <tbody>
      <?php foreach (HorarioAtencion::DIAS as $n => $dia): ?>
        <?php
        $franja = $valores['horario'][(string) $n] ?? null;
        $old = $conOld ? (old('horario')[$n] ?? []) : null;
        $abierto = $old !== null ? !empty($old['abierto']) : $franja !== null;
        ?>
        <tr>
          <td><?= e($dia) ?></td>
          <td><input class="form-check-input" type="checkbox" name="horario[<?= $n ?>][abierto]" value="1" <?= $abierto ? 'checked' : '' ?> aria-label="Atiende el <?= e($dia) ?>"></td>
          <td><input type="time" class="form-control form-control-sm" name="horario[<?= $n ?>][desde]" value="<?= e($old['desde'] ?? $franja['desde'] ?? '08:00') ?>" aria-label="Apertura <?= e($dia) ?>"></td>
          <td><input type="time" class="form-control form-control-sm" name="horario[<?= $n ?>][hasta]" value="<?= e($old['hasta'] ?? $franja['hasta'] ?? '18:00') ?>" aria-label="Cierre <?= e($dia) ?>"></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch" id="validar_horario" name="validar_horario" value="1" <?= $check('validar_horario') ?>>
      <label class="form-check-label" for="validar_horario">No permitir turnos fuera del horario ni en feriados</label>
    </div>
  </div>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'cupos_por_horario', 'etiqueta' => 'Turnos simultáneos por horario', 'tipo' => 'number', 'columna' => 'col-md-6 mb-3',
    'clase' => 'ancho-max-120', 'valor' => $valores['cupos_por_horario'], 'atributos' => ['min' => 1, 'max' => 20],
    'ayuda' => 'Por ejemplo, la cantidad de elevadores o técnicos disponibles.',
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'intervalo_minutos', 'etiqueta' => 'Franjas de la agenda semanal', 'tipo' => 'select', 'columna' => 'col-md-6 mb-3',
    'clase' => 'ancho-max-160', 'valor' => (int) old('intervalo_minutos', $valores['intervalo_minutos']), 'usarAnterior' => false,
    'opciones' => array_combine([15, 20, 30, 45, 60, 90, 120], array_map(fn($min) => "{$min} minutos", [15, 20, 30, 45, 60, 90, 120])),
  ]) ?>
</div>

<?= $view->partial('componentes/campo', [
  'nombre' => 'feriados', 'etiqueta' => 'Feriados y días no laborables (uno por línea, AAAA-MM-DD)', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'clase' => 'font-monospace', 'valor' => implode("\n", $valores['feriados']), 'atributos' => ['rows' => 6],
]) ?>

<h5 class="mt-4">Confirmación y recordatorios</h5>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="enviar_confirmacion" name="enviar_confirmacion" value="1" <?= $check('enviar_confirmacion') ?>>
  <label class="form-check-label" for="enviar_confirmacion">Al agendar o reprogramar un turno, pedirle al cliente que lo confirme</label>
</div>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="recordatorio_automatico" name="recordatorio_automatico" value="1" <?= $check('recordatorio_automatico') ?>>
  <label class="form-check-label" for="recordatorio_automatico">Enviar recordatorio automático el día hábil anterior al turno</label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'recordatorio_hora', 'etiqueta' => 'A partir de las', 'tipo' => 'time', 'columna' => 'mb-3', 'clase' => 'ancho-max-140',
  'valor' => $valores['recordatorio_hora'], 'ayuda' => 'Los recordatorios solo salen en días hábiles y dentro del horario de atención.',
]) ?>
