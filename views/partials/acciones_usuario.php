<?php
/**
 * Ayuda, usuario, modo oscuro y salir. Van en el encabezado (computadora) y en el menú del celular.
 *
 * @var string $clase clases de los botones
 * @var string $ayuda  link de Ayuda de esta pantalla (url_ayuda(), lo arma el layout)
 * @var bool|null $soloIcono  solo los íconos (menú del celular); el texto queda para lectores de pantalla
 */
$texto = !empty($soloIcono) ? 'visually-hidden' : '';
?>
<?php if ($usuarioActual = auth()->user()): ?>
  <?php // Abre el manual en la parte que explica la pantalla en la que se está. ?>
  <a href="<?= e($ayuda) ?>" class="<?= e($clase) ?>" title="Ayuda: cómo se usa esta pantalla">
    <?= icono('question-circle') ?> <span class="<?= $texto ?>">Ayuda</span>
  </a>
  <a href="<?= url('perfil/clave') ?>" class="<?= e($clase) ?>" title="<?= e($usuarioActual['nombre']) ?> · cambiar contraseña">
    <?= icono('person-circle') ?> <span class="<?= $texto ?>"><?= e($usuarioActual['nombre']) ?></span>
  </a>
<?php endif; ?>
<?php // Letra más chica / más grande (WCAG 1.4.4): se guarda en este navegador. ?>
<span class="btn-group" role="group" aria-label="Tamaño de la letra">
  <button class="<?= e($clase) ?> js-letra" type="button" data-paso="-1" title="Letra más chica" aria-label="Letra más chica">A−</button>
  <button class="<?= e($clase) ?> js-letra" type="button" data-paso="1" title="Letra más grande" aria-label="Letra más grande">A+</button>
</span>
<button class="<?= e($clase) ?> js-tema" type="button" title="Cambiar entre modo claro y oscuro">
  <?= icono('moon-stars') ?> <span class="js-tema-texto <?= $texto ?>">Modo oscuro</span>
</button>
<?php if ($usuarioActual): ?>
  <form action="<?= url('logout') ?>" method="POST" class="d-inline <?= !empty($soloIcono) ? 'ms-auto' : '' ?>">
    <?= csrf_field() ?>
    <?php // En el mostrador, salir deja el equipo listo para que entre el siguiente con su PIN. ?>
    <?php $enMostrador = auth()->enMostrador(); ?>
    <button type="submit" class="<?= e($clase) ?>" title="<?= $enMostrador ? 'Salir para que entre otro con su PIN' : 'Salir del sistema' ?>"><?= icono($enMostrador ? 'people' : 'box-arrow-right') ?> <span class="<?= $texto ?>"><?= $enMostrador ? 'Cambiar de usuario' : 'Salir' ?></span></button>
  </form>
<?php endif; ?>
