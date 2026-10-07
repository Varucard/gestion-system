<?php
/**
 * @var array<string, mixed>|null $marca  marca en edición
 * @var list<array<string, mixed>> $marcas
 */
?>
<div class="card mb-4 mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $marca ? 'Editar marca' : 'Nueva marca' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($marca ? "marcas/{$marca['id']}" : 'marcas') ?>" method="POST">
      <?= csrf_field() ?>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'nombre', 'etiqueta' => 'Nombre de la marca *', 'valor' => $marca['nombre'] ?? '', 'columna' => 'mb-3',
        'atributos' => ['minlength' => 2, 'maxlength' => 50, 'required' => true],
      ]) ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $marca ? 'Actualizar marca' : 'Registrar marca' ?></button>
      <?php if ($marca): ?>
        <a href="<?= url('marcas') ?>" class="btn btn-outline-secondary">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h4 class="mb-0">Marcas registradas</h4>
  </div>
  <div class="card-body">
    <table data-vacio="Todavía no hay marcas cargadas." class="table table-striped table-bordered js-datatable" data-order='[[0, "asc"]]'>
      <thead>
        <tr>
          <th>Nombre</th>
          <th data-orderable="false">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($marcas as $m): ?>
          <tr>
            <td><?= e($m['nombre']) ?></td>
            <td class="col-acciones text-nowrap">
              <?= boton_accion("marcas/{$m['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
              <?= $view->partial('partials/delete_button', [
                'action' => "marcas/{$m['id']}/eliminar",
                'label' => 'Eliminar',
                'confirm' => "¿Eliminar la marca \"{$m['nombre']}\"?",
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
