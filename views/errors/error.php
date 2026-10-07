<div class="text-center py-5">
  <h2 class="display-4"><?= (int) $status ?></h2>
  <p class="lead"><?= e($message) ?></p>
  <?php if (!empty($codigo)): ?>
    <p class="text-muted small">Código de error: <code><?= e($codigo) ?></code> (sirve para buscarlo en el registro del sistema).</p>
  <?php endif; ?>
  <a href="<?= url('/') ?>" class="btn btn-primary">Volver al inicio</a>
</div>
