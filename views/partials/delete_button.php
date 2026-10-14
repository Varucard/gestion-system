<?php
/**
 * Botón que envía un POST protegido por CSRF, con confirmación (modal).
 *
 * @var string $action   ruta destino
 * @var string $label    texto del botón
 * @var string|null $confirm  mensaje de confirmación; null = sin preguntar (lo que se puede deshacer)
 * @var string $class    clases del botón (opcional; por defecto, rojo de eliminar)
 * @var string $icono    ícono de Bootstrap Icons (opcional; por defecto, papelera)
 */
?>
<form action="<?= url($action) ?>" method="POST" class="d-inline"<?= !empty($confirm) ? ' data-confirm="' . e($confirm) . '" data-confirm-aceptar="Sí, ' . e(mb_strtolower($label)) . '"' : '' ?>>
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-sm btn-accion <?= e($class ?? 'btn-outline-danger') ?>" title="<?= e($label) ?>"><?= icono($icono ?? 'trash') ?><span class="btn-texto"> <?= e($label) ?></span></button>
</form>
