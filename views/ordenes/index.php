<?php
/** @var list<\App\Enums\EstadoOrden> $estados */
$view->script('ordenes.js');
$filtro = ['' => 'Todas', 'abiertas' => 'Abiertas (pendientes y en proceso)', 'con_saldo' => 'Con saldo pendiente'];
foreach ($estados as $e) {
  $filtro[$e->value] = $e->label();
}
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Órdenes registradas</h4>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?= $view->partial('partials/filtro_estado', ['id' => 'filtro_ordenes', 'nombre' => 'estado', 'etiqueta' => 'Mostrar', 'opciones' => $filtro]) ?>
      <a href="<?= url('ordenes/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Crear orden</a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-vacio="Todavía no hay órdenes registradas." class="table table-striped table-bordered" data-server="<?= e(url('ordenes/datos')) ?>" data-filtros="#filtro_ordenes" data-order='[[0, "desc"]]'>
        <thead>
          <tr>
            <th>N°</th>
            <th class="col-cliente">Cliente</th>
            <th class="col-equipo">Equipo</th>
            <th data-orderable="false">Servicio(s) - Repuesto(s)</th>
            <th>Total</th>
            <th>Saldo</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th data-orderable="false">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
