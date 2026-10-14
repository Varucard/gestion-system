<?php
/**
 * Entrada en un mostrador: se toca el nombre y se escribe el PIN (con el teclado o con los
 * números grandes de la pantalla, pensados para la tablet). Con el cuarto número entra solo.
 *
 * @var list<array{id: int|string, nombre: string}> $usuarios
 * @var array<string, mixed> $mostrador
 */
$view->script('mostrador.js');
$elegido = (int) old('usuario_id', 0);
?>
<form action="<?= url('login/pin') ?>" method="POST" class="entrada-pin" id="entrada_pin">
  <?= csrf_field() ?>
  <fieldset>
    <legend class="visually-hidden">¿Quién sos?</legend>
    <div class="d-grid gap-2 mb-3 usuarios-pin">
      <?php foreach ($usuarios as $u): ?>
        <input type="radio" class="btn-check" name="usuario_id" id="usuario_<?= (int) $u['id'] ?>" value="<?= (int) $u['id'] ?>" required <?= $elegido === (int) $u['id'] ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary btn-lg text-start" for="usuario_<?= (int) $u['id'] ?>"><?= icono('person-circle') ?> <?= e($u['nombre']) ?></label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div class="js-paso-pin" <?= $elegido ? '' : 'hidden' ?>>
    <label for="pin" class="form-label">Tu PIN</label>
    <input type="password" class="form-control form-control-lg text-center pin-campo" id="pin" name="pin" inputmode="numeric" pattern="\d{4}"
      maxlength="4" autocomplete="off" required data-error="El PIN son 4 números.">
    <div class="teclado-pin mt-3" aria-hidden="true">
      <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n): ?>
        <button type="button" class="btn btn-outline-secondary btn-lg js-tecla" tabindex="-1" data-tecla="<?= $n ?>"><?= $n ?></button>
      <?php endforeach; ?>
      <button type="button" class="btn btn-outline-secondary btn-lg js-tecla" tabindex="-1" data-tecla="borrar" title="Borrar"><?= icono('backspace') ?></button>
      <button type="button" class="btn btn-outline-secondary btn-lg js-tecla" tabindex="-1" data-tecla="0">0</button>
      <button type="submit" class="btn btn-primary btn-lg" tabindex="-1" title="Entrar"><?= icono('arrow-right') ?></button>
    </div>
  </div>
</form>
<p class="small text-muted text-center mt-3 mb-0">
  <?= e($mostrador['nombre']) ?> ·
  <a href="<?= url('login') ?>?con_clave=1">Entrar con usuario y contraseña</a>
</p>
