<?php
/**
 * @var array<string, mixed> $valores
 * @var list<\App\Notificaciones\CanalNotificacion> $canales
 */
$conOld = session()->hasOldInput();
$activos = $conOld ? (array) old('canales', []) : $valores['canales'];
$etiquetas = ['email' => 'Email', 'whatsapp' => 'WhatsApp Business (API)'];
?>
<h5>Canales para confirmaciones y recordatorios</h5>
<p class="text-muted small">Se usa el primer canal disponible para el que el cliente tenga datos de contacto.</p>
<?php foreach ($canales as $canal): ?>
  <?php $nombre = $canal->nombre(); ?>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" id="canal_<?= e($nombre) ?>" name="canales[]" value="<?= e($nombre) ?>"
      <?= in_array($nombre, $activos, true) ? 'checked' : '' ?> <?= $nombre === 'whatsapp' ? 'disabled' : '' ?>>
    <label class="form-check-label" for="canal_<?= e($nombre) ?>">
      <?= e($etiquetas[$nombre] ?? $nombre) ?>
      <?php if ($canal->disponible()): ?>
        <span class="badge bg-success">listo</span>
      <?php elseif ($nombre === 'email'): ?>
        <span class="badge bg-warning text-dark">falta configurar MAIL_DSN en .env</span>
      <?php else: ?>
        <span class="badge bg-secondary">próximamente</span>
      <?php endif; ?>
    </label>
  </div>
<?php endforeach; ?>

<div class="form-check form-switch mt-3">
  <input class="form-check-input" type="checkbox" role="switch" id="avisar_negocio" name="avisar_negocio" value="1"
    <?= ($conOld ? old('avisar_negocio') : $valores['avisar_negocio']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="avisar_negocio">Avisarnos por email cuando un cliente acepta o rechaza un presupuesto</label>
</div>

<div class="form-check form-switch mt-2">
  <input class="form-check-input" type="checkbox" role="switch" id="avisar_listo" name="avisar_listo" value="1"
    <?= ($conOld ? old('avisar_listo') : $valores['avisar_listo']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="avisar_listo">Avisarle al cliente que el equipo está listo cuando la orden pasa a "Finalizado" (una sola vez por orden)</label>
</div>

<h5 class="mt-4">WhatsApp manual</h5>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="boton_whatsapp_manual" name="boton_whatsapp_manual" value="1"
    <?= ($conOld ? old('boton_whatsapp_manual') : $valores['boton_whatsapp_manual']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="boton_whatsapp_manual">Mostrar el botón "WhatsApp" que abre la conversación con el recordatorio escrito</label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'codigo_pais', 'etiqueta' => 'Código de país', 'columna' => 'mb-3', 'clase' => 'ancho-max-100',
  'valor' => $valores['codigo_pais'], 'atributos' => ['maxlength' => 3],
  'ayuda' => 'Argentina: 54 (a los celulares se les agrega el 9 automáticamente).',
]) ?>
