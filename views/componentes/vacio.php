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
 */
?>
<div class="vacio">
  <?= icono($icono) ?>
  <p class="mb-2"><?= e($texto) ?></p>
  <?php if (!empty($accion)): ?>
    <a href="<?= url($accion[0]) ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> <?= e($accion[1]) ?></a>
  <?php endif; ?>
</div>
