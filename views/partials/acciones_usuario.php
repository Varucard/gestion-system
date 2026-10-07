<?php
/**
 * Usuario, salir y modo oscuro. Van en el encabezado (computadora) y en el menú del celular.
 *
 * @var string $clase clases de los botones
 */
?>
<?php if ($usuarioActual = auth()->user()): ?>
  <a href="<?= url('perfil/clave') ?>" class="<?= e($clase) ?>" title="Cambiar contraseña"><?= icono('person-circle') ?> <?= e($usuarioActual['nombre']) ?></a>
  <form action="<?= url('logout') ?>" method="POST" class="d-inline">
    <?= csrf_field() ?>
    <button type="submit" class="<?= e($clase) ?>">Salir</button>
  </form>
<?php endif; ?>
<button class="<?= e($clase) ?> js-tema" type="button"><?= icono('moon-stars') ?> <span class="js-tema-texto">Modo oscuro</span></button>
