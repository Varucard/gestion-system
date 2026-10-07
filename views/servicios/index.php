<?php
/**
 * @var array<string, mixed>|null $servicio  servicio en edición
 * @var list<array<string, mixed>> $servicios
 */
?>
<div class="card mb-4 mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $servicio ? 'Editar servicio' : 'Nuevo servicio' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($servicio ? "servicios/{$servicio['id']}" : 'servicios') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'valor' => $servicio['nombre'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['minlength' => 2, 'required' => true],
        ]) ?>
        <div class="col-md-6">
          <label for="precio_base" class="form-label">Precio base *</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" class="form-control" id="precio_base" name="precio_base" step="0.01" min="0" required
              value="<?= e(old('precio_base', $servicio['precio_base'] ?? '')) ?>">
          </div>
        </div>
      </div>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'valor' => $servicio['descripcion'] ?? '', 'columna' => 'mb-3',
        'atributos' => ['rows' => 3],
      ]) ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $servicio ? 'Actualizar servicio' : 'Registrar servicio' ?></button>
      <?php if ($servicio): ?>
        <a href="<?= url('servicios') ?>" class="btn btn-outline-secondary">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h4 class="mb-0">Servicios registrados</h4>
  </div>
  <div class="card-body">
    <table data-vacio="Todavía no hay servicios cargados." class="table table-striped table-bordered js-datatable" data-order='[[0, "asc"]]'>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Precio base</th>
          <th data-orderable="false">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($servicios as $row): ?>
          <tr>
            <td><?= e($row['nombre']) ?></td>
            <td><?= e($row['descripcion'] ?? '') ?></td>
            <td data-order="<?= (float) $row['precio_base'] ?>"><?= importe($row['precio_base']) ?></td>
            <td class="col-acciones text-nowrap">
              <?= boton_accion("servicios/{$row['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
              <?= $view->partial('partials/delete_button', [
                'action' => "servicios/{$row['id']}/eliminar",
                'label' => 'Eliminar',
                'confirm' => "¿Eliminar el servicio \"{$row['nombre']}\"?",
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
