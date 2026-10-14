<?php
/**
 * Fotos del equipo con la cámara del celular (o elegidas de la galería / la PC). Se pueden
 * sacar de a una: se van sumando. El navegador las achica antes de subirlas (fotos.js), así
 * suben rápido aunque la señal sea mala.
 *
 * @var string|null $ayuda
 */
use App\Controllers\RecepcionController;

$view->script('fotos.js');
?>
<div class="fotos-equipo" data-fotos data-maximo="<?= RecepcionController::MAX_FOTOS ?>">
  <label class="btn btn-outline-secondary btn-lg">
    <?= icono('camera') ?> Sacar o elegir fotos
    <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp,image/*" multiple class="visually-hidden js-fotos-campo">
  </label>
  <div class="fotos-vista mt-2" aria-live="polite"></div>
  <small class="form-text text-muted d-block"><?= e($ayuda ?? 'Golpes, rayones, la pantalla y los accesorios que trae. Hasta ' . RecepcionController::MAX_FOTOS . ' fotos.') ?></small>
</div>
