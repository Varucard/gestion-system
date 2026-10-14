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

<?php // PIN del modo mostrador: en la PC o tablet que comparten varios, se entra tocando el nombre y con 4 números. ?>
<div class="card mt-3 ancho-max-640" id="pin">
  <div class="card-header">
    <h4 class="mb-0">PIN para el mostrador</h4>
  </div>
  <div class="card-body">
    <p class="text-muted">
      En los equipos que el administrador habilitó como mostrador, entrás tocando tu nombre y escribiendo estos 4 números,
      sin usuario ni contraseña. La sesión se cierra sola a los <?= (int) (\App\Services\MostradorService::INACTIVIDAD / 60) ?> minutos sin uso.
    </p>
    <?php if (!empty($usuario['tiene_pin'])): ?>
      <p class="text-success"><?= icono('check-circle-fill') ?> Ya tenés un PIN. Podés cambiarlo o quitarlo.</p>
    <?php endif; ?>
    <?php foreach (session()->messages()['error_pin'] ?? [] as $mensaje): ?>
      <div class="alert alert-danger" role="alert"><?= icono('exclamation-triangle') ?> <?= e($mensaje) ?></div>
    <?php endforeach; ?>
    <form action="<?= url('perfil/pin') ?>" method="POST" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-sm-4">
        <label for="pin_nuevo" class="form-label">PIN nuevo *</label>
        <input type="password" class="form-control" id="pin_nuevo" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" required data-error="El PIN son 4 números.">
      </div>
      <div class="col-sm-4">
        <label for="pin_confirmacion" class="form-label">Repetilo *</label>
        <input type="password" class="form-control" id="pin_confirmacion" name="pin_confirmacion" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" required>
      </div>
      <div class="col-sm-4">
        <label for="clave_actual_pin" class="form-label">Tu contraseña *</label>
        <input type="password" class="form-control" id="clave_actual_pin" name="clave_actual_pin" autocomplete="current-password" required>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-seccion"><?= icono('grid-3x3-gap') ?> Guardar el PIN</button>
      </div>
    </form>
    <?php if (!empty($usuario['tiene_pin'])): ?>
      <form action="<?= url('perfil/pin/quitar') ?>" method="POST" class="mt-2" data-confirm="¿Quitar tu PIN? En el mostrador vas a entrar con usuario y contraseña." data-confirm-aceptar="Sí, quitar el PIN">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-link text-danger p-0">Quitar mi PIN</button>
      </form>
    <?php endif; ?>
  </div>
</div>

