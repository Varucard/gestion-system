<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Equipos registrados</h4>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?= $view->partial('partials/filtro_estado', ['id' => 'filtro_equipos', 'nombre' => 'estado', 'etiqueta' => 'Mostrar', 'opciones' => ['' => 'Todos', 'activo' => 'Activos', 'inactivo' => 'Inactivos']]) ?>
      <a href="<?= url('equipos/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo equipo</a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-vacio="Todavía no hay equipos cargados." data-vacio-accion="<?= e(url('recepcion')) ?>" data-vacio-boton="Recibir el primer equipo" class="table table-striped table-bordered" data-server="<?= e(url('equipos/datos')) ?>" data-filtros="#filtro_equipos" data-order='[[0, "asc"]]'>
        <thead>
          <tr>
            <th data-prioridad="3">Cliente</th>
            <th>Tipo</th>
            <th>Marca</th>
            <th data-prioridad="1">Modelo</th>
            <th>N° de serie</th>
            <th>Estado</th>
            <th data-orderable="false" data-prioridad="2">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
