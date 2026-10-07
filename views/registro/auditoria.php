<?php
/**
 * @var list<string> $entidades
 * @var list<array<string, mixed>> $usuarios
 */
?>
<div class="card mt-3">
  <div class="card-header"><h4 class="mb-0">Auditoría: quién hizo qué y cuándo</h4></div>
  <div class="card-body">
    <form id="filtros_auditoria" class="row g-2 mb-3">
      <div class="col-sm-3">
        <label for="f_entidad" class="form-label small">Sobre</label>
        <select id="f_entidad" name="entidad" class="form-select form-select-sm">
          <option value="">Todo</option>
          <?php foreach ($entidades as $entidad): ?>
            <option value="<?= e($entidad) ?>"><?= e($entidad) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label for="f_usuario" class="form-label small">Usuario</label>
        <select id="f_usuario" name="usuario_id" class="form-select form-select-sm">
          <option value="">Todos</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label for="f_desde" class="form-label small">Desde</label>
        <input type="date" id="f_desde" name="desde" class="form-control form-control-sm">
      </div>
      <div class="col-sm-3">
        <label for="f_hasta" class="form-label small">Hasta</label>
        <input type="date" id="f_hasta" name="hasta" class="form-control form-control-sm">
      </div>
    </form>
    <div class="table-responsive">
      <table data-vacio="No hay movimientos para esos filtros." class="table table-striped table-bordered table-sm" data-server="<?= e(url('auditoria/datos')) ?>" data-filtros="#filtros_auditoria" data-order='[[0, "desc"]]'>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Sobre</th>
            <th>Descripción</th>
            <th data-orderable="false">Detalle</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
