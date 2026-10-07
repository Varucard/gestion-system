<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Clientes registrados</h4>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?= $view->partial('partials/filtro_estado', ['id' => 'filtro_clientes', 'nombre' => 'estado', 'etiqueta' => 'Mostrar', 'opciones' => ['' => 'Todos', 'activo' => 'Activos', 'inactivo' => 'Inactivos']]) ?>
      <a href="<?= url('clientes/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo cliente</a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-vacio="Todavía no hay clientes registrados." class="table table-striped table-bordered" data-server="<?= e(url('clientes/datos')) ?>" data-filtros="#filtro_clientes" data-order='[[1, "asc"]]'>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>DNI</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Dirección</th>
            <th data-orderable="false">Saldo</th>
            <th>Estado</th>
            <th data-orderable="false">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
