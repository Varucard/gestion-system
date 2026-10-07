<div class="card mt-3 ancho-max-640">
  <div class="card-header">
    <h4 class="mb-0">Cambiar mi contraseña</h4>
  </div>
  <div class="card-body">
    <form action="<?= url('perfil/clave') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="clave_actual" class="form-label">Contraseña actual *</label>
        <input type="password" class="form-control" id="clave_actual" name="clave_actual" autocomplete="current-password" required>
      </div>
      <?= $view->partial('usuarios/_campos_clave', ['requerida' => true]) ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> Actualizar contraseña</button>
    </form>
  </div>
</div>
