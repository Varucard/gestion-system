<?php /** @var list<array<string, mixed>> $proveedores */ ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Proveedores</h4>
    <a href="<?= url('proveedores/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo proveedor</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-vacio="Todavía no hay proveedores cargados." class="table table-striped table-bordered js-datatable" data-order='[[0, "asc"]]'>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>CUIT</th>
            <th>Contacto</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Repuestos</th>
            <th>Estado</th>
            <th data-orderable="false">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($proveedores as $p): ?>
            <tr>
              <td><?= e($p['nombre']) ?></td>
              <td><?= e($p['cuit'] ?? '') ?></td>
              <td><?= e($p['contacto'] ?? '') ?></td>
              <td><?= e($p['telefono'] ?? '') ?></td>
              <td><?= $p['email'] ? '<a href="mailto:' . e($p['email']) . '">' . e($p['email']) . '</a>' : '' ?></td>
              <td><?= (int) $p['repuestos'] ?></td>
              <td><span class="badge bg-<?= $p['activo'] ? 'success' : 'secondary' ?>"><?= $p['activo'] ? 'activo' : 'inactivo' ?></span></td>
              <td class="col-acciones text-nowrap">
                <?= boton_accion("proveedores/{$p['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
                <?= $view->partial('partials/delete_button', [
                  'action' => "proveedores/{$p['id']}/eliminar",
                  'label' => 'Eliminar',
                  'confirm' => "¿Eliminar el proveedor \"{$p['nombre']}\"?",
                ]) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
