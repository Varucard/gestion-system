<?php
/**
 * Mensajes de la petición anterior. Los de éxito son avisos flotantes que se van solos;
 * los errores quedan en la página, arriba del formulario, hasta que se corrigen.
 */
$mensajes = session()->messages();
?>
<?php foreach ($mensajes['aviso'] ?? [] as $mensaje): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="status">
    <?= icono('info-circle') ?> <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>
<?php foreach ($mensajes['error'] ?? [] as $mensaje): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= icono('exclamation-triangle') ?> <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>
<?php // El contenedor va siempre (aunque esté vacío): los avisos que agrega JS (window.avisoExito) se anuncian. ?>
  <div class="avisos" aria-live="polite">
    <?php foreach ($mensajes['deshacer'] ?? [] as $json): ?>
      <?php $d = json_decode($json, true); ?>
      <?php // "Hecho · Deshacer": queda más tiempo (15 s) y no se va mientras el mouse está encima. ?>
      <div class="toast show align-items-center mb-2 js-aviso" role="status" aria-atomic="true" data-demora="15000">
        <div class="d-flex align-items-center gap-2 p-3">
          <span class="text-success"><?= icono('check-circle-fill') ?></span>
          <div class="flex-grow-1"><?= e($d['mensaje']) ?></div>
          <form action="<?= url($d['accion']) ?>" method="POST" class="d-inline">
            <?= csrf_field() ?>
            <?php foreach ($d['campos'] as $campo => $valor): ?>
              <input type="hidden" name="<?= e($campo) ?>" value="<?= e((string) $valor) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><?= icono('arrow-counterclockwise') ?> Deshacer</button>
          </form>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
      </div>
    <?php endforeach; ?>
    <?php foreach ($mensajes['success'] ?? [] as $mensaje): ?>
      <div class="toast show align-items-center mb-2 js-aviso" role="status" aria-atomic="true">
        <div class="d-flex align-items-center gap-2 p-3">
          <span class="text-success"><?= icono('check-circle-fill') ?></span>
          <div class="flex-grow-1"><?= e($mensaje) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
