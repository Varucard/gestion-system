<?php /** @var list<array<string, mixed>> $usuarios */ ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Usuarios del sistema</h4>
    <a href="<?= url('usuarios/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo usuario</a>
  </div>
  <div class="card-body">
    <table data-vacio="Todavía no hay usuarios." class="table table-striped table-bordered js-datatable" data-order='[[0, "asc"]]'>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Usuario</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Último acceso</th>
          <th data-orderable="false">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><?= e($u['nombre']) ?></td>
            <td><?= e($u['usuario']) ?></td>
            <td><?= e(\App\Enums\Rol::from($u['rol'])->label()) ?></td>
            <td><span class="badge bg-<?= $u['activo'] ? 'success' : 'secondary' ?>"><?= $u['activo'] ? 'activo' : 'inactivo' ?></span></td>
            <td data-order="<?= e($u['ultimo_acceso'] ?? '') ?>"><?= $u['ultimo_acceso'] ? format_date($u['ultimo_acceso'], 'd/m/Y H:i') : '—' ?></td>
            <td><?= boton_accion("usuarios/{$u['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
