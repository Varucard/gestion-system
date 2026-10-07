<?php /** @var list<array<string, mixed>> $deudores */ ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Clientes con saldo pendiente</h4>
    <strong>Total adeudado: <?= importe(array_sum(array_column($deudores, 'saldo'))) ?></strong>
  </div>
  <div class="card-body">
    <p class="text-muted small">Se consideran las órdenes finalizadas con pagos incompletos.</p>
    <table data-vacio="No hay clientes con deuda." class="table table-striped table-bordered js-datatable" data-order='[[3, "desc"]]'>
      <thead>
        <tr><th>Cliente</th><th>Teléfono</th><th>Órdenes</th><th>Saldo</th><th data-orderable="false"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($deudores as $d): ?>
          <tr>
            <td><?= e($d['cliente']) ?></td>
            <td><?= e($d['telefono']) ?></td>
            <td><?= (int) $d['ordenes'] ?></td>
            <td data-order="<?= (float) $d['saldo'] ?>" class="text-danger"><?= importe($d['saldo']) ?></td>
            <td><?= boton_accion("clientes/{$d['id']}", 'eye', 'Ver ficha') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
