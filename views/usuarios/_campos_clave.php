<?php /** @var bool $requerida */ ?>
<div class="row">
  <div class="col-md-6 mb-3">
    <label for="clave" class="form-label">Contraseña<?= $requerida ? ' *' : '' ?></label>
    <input type="password" class="form-control" id="clave" name="clave" autocomplete="new-password"
      minlength="<?= \App\Services\UsuarioService::LARGO_MINIMO_CLAVE ?>" <?= $requerida ? 'required' : '' ?>>
    <small class="form-text text-muted">
      <?= $requerida ? 'Mínimo ' . \App\Services\UsuarioService::LARGO_MINIMO_CLAVE . ' caracteres.' : 'Dejar vacío para no cambiarla.' ?>
    </small>
  </div>
  <div class="col-md-6 mb-3">
    <label for="clave_confirmacion" class="form-label">Repetir contraseña<?= $requerida ? ' *' : '' ?></label>
    <input type="password" class="form-control" id="clave_confirmacion" name="clave_confirmacion" autocomplete="new-password"
      <?= $requerida ? 'required' : '' ?>>
  </div>
</div>
