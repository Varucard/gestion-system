<?php
/**
 * Mensajes de la petición anterior. Los de éxito son avisos flotantes que se van solos;
 * los errores quedan en la página, arriba del formulario, hasta que se corrigen.
 */
$mensajes = session()->messages();
?>
<?php foreach ($mensajes['error'] ?? [] as $mensaje): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= icono('exclamation-triangle') ?> <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>
<?php if (!empty($mensajes['success'])): ?>
  <div class="avisos" aria-live="polite">
    <?php foreach ($mensajes['success'] as $mensaje): ?>
      <div class="toast show align-items-center mb-2 js-aviso" role="status" aria-atomic="true">
        <div class="d-flex align-items-center gap-2 p-3">
          <span class="text-success"><?= icono('check-circle-fill') ?></span>
          <div class="flex-grow-1"><?= e($mensaje) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
