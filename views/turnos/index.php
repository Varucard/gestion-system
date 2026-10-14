<?php
/** @var list<\App\Enums\EstadoTurno> $estados */
$view->script('turnos.js');
$porEstado = ['' => 'Todos los estados'];
foreach ($estados as $e) {
  $porEstado[$e->value] = $e->label();
}
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Turnos registrados</h4>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <form id="filtro_turnos" class="d-flex gap-2">
        <select name="periodo" class="form-select form-select-sm w-auto" aria-label="Período">
          <option value="proximos">Próximos</option>
          <option value="pasados">Pasados</option>
          <option value="">Todos</option>
        </select>
        <select name="estado" class="form-select form-select-sm w-auto" aria-label="Estado">
          <?php foreach ($porEstado as $valor => $texto): ?>
            <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="<?= url('turnos/semana') ?>" class="btn btn-outline-secondary">Agenda semanal</a>
      <a href="<?= url('turnos/crear') ?>" class="btn btn-seccion"><?= icono('plus-lg') ?> Nuevo turno</a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table data-tarjetas data-vacio="No hay turnos para mostrar." data-vacio-accion="<?= e(url('turnos/crear')) ?>" data-vacio-boton="Nuevo turno" class="table table-striped table-bordered" data-server="<?= e(url('turnos/datos')) ?>" data-filtros="#filtro_turnos" data-order='[[0, "asc"]]'>
        <thead>
          <tr>
            <th data-prioridad="1">Fecha / Hora</th>
            <?php // En la tarjeta del celular, el cliente ya va debajo de la fecha. ?>
            <th class="col-cliente" data-prioridad="4" data-tarjeta="ocultar">Cliente</th>
            <th class="col-equipo">Equipo</th>
            <th>Descripción</th>
            <th data-prioridad="3">Estado</th>
            <th data-orderable="false" data-prioridad="2">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
