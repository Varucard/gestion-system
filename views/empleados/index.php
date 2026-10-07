<?php /** @var list<array<string, mixed>> $empleados */ ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Empleados</h4>
    <a href="<?= url('empleados/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo empleado</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-vacio="Todavía no hay empleados cargados." class="table table-striped table-bordered js-datatable" data-order='[[0, "asc"]]'>
        <thead>
          <tr><th>Apellido y nombre</th><th>DNI</th><th>Puesto</th><th>Teléfono</th><th>Ingreso</th><th>Órdenes</th><th>Estado</th><th data-orderable="false">Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($empleados as $em): ?>
            <?php $activo = $em['estado'] === 'activo'; ?>
            <tr>
              <td><?= e("{$em['apellido']}, {$em['nombre']}") ?></td>
              <td><?= e($em['dni']) ?></td>
              <td><?= e($em['puesto']) ?></td>
              <td><?= e($em['telefono'] ?? '') ?></td>
              <td data-order="<?= e($em['fecha_ingreso'] ?? '') ?>"><?= format_date($em['fecha_ingreso']) ?></td>
              <td><?= (int) $em['ordenes'] ?></td>
              <td><span class="badge bg-<?= $activo ? 'success' : 'secondary' ?>"><?= e($em['estado']) ?></span></td>
              <td class="col-acciones text-nowrap">
                <?= boton_accion("empleados/{$em['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
                <?= $view->partial('partials/delete_button', [
                  'action' => "empleados/{$em['id']}/estado",
                  'label' => $activo ? 'Dar de baja' : 'Reactivar',
                  'class' => $activo ? 'btn-outline-warning' : 'btn-outline-success',
                  'icono' => $activo ? 'pause-circle' : 'play-circle',
                  'confirm' => ($activo ? '¿Dar de baja a ' : '¿Reactivar a ') . "{$em['nombre']} {$em['apellido']}?",
                ]) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
