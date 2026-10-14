<?php
/**
 * Error del servidor debajo de un control que no usa componentes/campo (p. ej. un desplegable
 * con buscador armado a mano). Va justo después del control.
 *
 * @var string $nombre name del control
 */
?>
<?php if (($errorCampo = error_campo($nombre)) !== null): ?>
  <div class="invalid-feedback d-block js-error-campo"><?= e($errorCampo) ?></div>
<?php endif; ?>
