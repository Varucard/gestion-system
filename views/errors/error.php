<?php
/**
 * Página de error. El número va chico (sirve para soporte); lo grande es qué pasó y qué hacer.
 *
 * @var int $status
 * @var string $message
 * @var string|null $codigo
 */
$iconos = [403 => 'shield-lock', 404 => 'signpost-split'];
?>
<div class="text-center py-5">
  <div class="display-4 text-muted mb-2"><?= icono($iconos[$status] ?? 'exclamation-octagon') ?></div>
  <p class="lead mx-auto ancho-max-640"><?= e($message) ?></p>
  <?php if (!empty($codigo)): ?>
    <p class="text-muted small">Código de error: <code><?= e($codigo) ?></code> (sirve para buscarlo en el registro del sistema).</p>
  <?php else: ?>
    <p class="text-muted small">Error <?= (int) $status ?></p>
  <?php endif; ?>
  <div class="d-flex flex-wrap gap-2 justify-content-center">
    <?php // Desde el mostrador, lo que falta es entrar con usuario y contraseña: el botón lleva directo. ?>
    <?php if ($status === 403 && auth()->enMostrador()): ?>
      <form action="<?= url('logout') ?>" method="POST" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary"><?= icono('people') ?> Cambiar de usuario</button>
      </form>
      <a href="<?= url('/') ?>" class="btn btn-outline-secondary">Volver al inicio</a>
    <?php else: ?>
      <a href="<?= url('/') ?>" class="btn btn-primary">Volver al inicio</a>
    <?php endif; ?>
  </div>
</div>
