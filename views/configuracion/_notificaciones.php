<?php
/**
 * @var array<string, mixed> $valores
 * @var list<\App\Notificaciones\CanalNotificacion> $canales
 */
$conOld = session()->hasOldInput();
$activos = $conOld ? (array) old('canales', []) : $valores['canales'];
$etiquetas = ['email' => 'Email'];
?>
<h5>Avisos automáticos</h5>
<p class="text-muted small">Confirmación y recordatorio de turnos, presupuesto, equipo listo y próximo mantenimiento. Salen por email a los clientes que lo tienen cargado.</p>
<?php foreach ($canales as $canal): ?>
  <?php $nombre = $canal->nombre(); ?>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" id="canal_<?= e($nombre) ?>" name="canales[]" value="<?= e($nombre) ?>"
      <?= in_array($nombre, $activos, true) ? 'checked' : '' ?>>
    <label class="form-check-label" for="canal_<?= e($nombre) ?>">
      <?= e($etiquetas[$nombre] ?? $nombre) ?>
      <?php if ($canal->disponible()): ?>
        <span class="badge bg-success">listo</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark">falta configurar MAIL_DSN en .env</span>
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
  <label class="form-check-label" for="avisar_listo">Avisarle al cliente que el equipo está listo cuando la orden pasa a "Listo" (una sola vez por orden)</label>
</div>

<h5 class="mt-4">WhatsApp</h5>
<p class="text-muted small">Los botones de WhatsApp abren la conversación con el cliente con el mensaje ya escrito (el texto está en <a href="<?= url('configuracion/mensajes') ?>">Mensajes</a>), con el link para aceptar el presupuesto o para seguir el equipo. Se envía desde el teléfono o la PC, como cualquier mensaje: no tiene costo.</p>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="boton_whatsapp_manual" name="boton_whatsapp_manual" value="1"
    <?= ($conOld ? old('boton_whatsapp_manual') : $valores['boton_whatsapp_manual']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="boton_whatsapp_manual">Mostrar los botones de WhatsApp (turnos, presupuesto, equipo listo y mantenimientos)</label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'whatsapp_abrir', 'etiqueta' => 'En la computadora, los botones de WhatsApp abren…', 'tipo' => 'select', 'columna' => 'mb-3',
  'clase' => 'ancho-max-420', 'valor' => $valores['whatsapp_abrir'],
  'opciones' => [
    'app' => 'La app de WhatsApp instalada en la computadora (recomendado si está instalada)',
    'web' => 'WhatsApp Web, en una pestaña nueva',
    'wame' => 'Preguntar cada vez (página de WhatsApp para elegir)',
  ],
  'ayuda' => 'En el celular abren siempre la app. En la computadora, la app de escritorio abre la conversación sin pasar por el navegador ni preguntar nada. '
    . 'WhatsApp Web se abre en otra pestaña cada vez: si ya había una abierta, WhatsApp pregunta «¿Usar aquí?» (es una limitación de WhatsApp).',
]) ?>
<?= $view->partial('componentes/campo', [
  'nombre' => 'codigo_pais', 'etiqueta' => 'Código de país', 'columna' => 'mb-3', 'clase' => 'ancho-max-100',
  'valor' => $valores['codigo_pais'], 'atributos' => ['maxlength' => 3],
  'ayuda' => 'Argentina: 54 (a los celulares se les agrega el 9 automáticamente).',
]) ?>
