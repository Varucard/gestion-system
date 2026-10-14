<?php
/**
 * Estado vacío: ícono, texto y, opcional, el botón para crear el primero.
 *
 *   <?= $view->partial('componentes/vacio', ['icono' => 'clipboard', 'texto' => 'Todavía no hay órdenes.',
 *     'accion' => ['ordenes/crear', 'Crear la primera orden']]) ?>
 *
 * @var string $icono                  ícono de Bootstrap Icons
 * @var string $texto
 * @var array{0: string, 1: string}|null $accion  [ruta, texto del botón]
 * @var bool|null $compacto  una sola línea, sin el ícono grande (p. ej., las listas del inicio)
 */
?>
<?php if (!empty($compacto)): ?>
  <p class="vacio-compacto mb-0"><?= icono($icono) ?> <?= e($texto) ?></p>
  <?php return; ?>
<?php endif; ?>
<div class="vacio">
  <?= icono($icono) ?>
  <p class="mb-2"><?= e($texto) ?></p>
  <?php if (!empty($accion)): ?>
    <a href="<?= url($accion[0]) ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> <?= e($accion[1]) ?></a>
  <?php endif; ?>
</div>
