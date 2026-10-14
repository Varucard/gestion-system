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
      <table data-vacio="Todavía no hay clientes cargados." data-vacio-accion="<?= e(url('clientes/crear')) ?>" data-vacio-boton="Cargar el primero"
        class="table table-striped" data-server="<?= e(url('clientes/datos')) ?>" data-filtros="#filtro_clientes" data-order='[[0, "asc"]]'>
        <thead>
          <tr>
            <th data-prioridad="1">Cliente</th>
            <th>Teléfono</th>
            <th data-orderable="false" data-prioridad="3">Saldo</th>
            <th>Estado</th>
            <th data-orderable="false" data-prioridad="2">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
