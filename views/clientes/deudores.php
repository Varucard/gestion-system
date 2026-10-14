<?php /** @var list<array<string, mixed>> $deudores */ ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Clientes con saldo pendiente</h4>
    <strong>Total adeudado: <?= importe(array_sum(array_column($deudores, 'saldo'))) ?></strong>
  </div>
  <div class="card-body">
    <p class="text-muted small">Equipos ya listos con el pago incompleto. «Cobrar» abre la orden impaga más vieja del cliente.</p>
    <table data-vacio="No hay clientes con deuda." class="table table-striped js-datatable" data-order='[[1, "desc"]]'>
      <thead>
        <tr>
          <th data-prioridad="1">Cliente</th>
          <th data-prioridad="2">Saldo</th>
          <th data-prioridad="5">Teléfono</th>
          <th data-prioridad="6">Órdenes</th>
          <th data-orderable="false" data-prioridad="3">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deudores as $d): ?>
          <tr>
            <td><a href="<?= url("clientes/{$d['id']}") ?>"><?= e($d['cliente']) ?></a></td>
            <td data-order="<?= (float) $d['saldo'] ?>" class="text-danger fw-semibold"><?= importe($d['saldo']) ?></td>
            <td><?= e($d['telefono']) ?></td>
            <td><?= (int) $d['ordenes'] ?></td>
            <td class="text-nowrap">
              <a href="<?= url("ordenes/{$d['orden_a_cobrar']}#registrar_pago") ?>" class="btn btn-sm btn-seccion"><?= icono('cash-coin') ?> Cobrar</a>
              <?= boton_accion("clientes/{$d['id']}", 'eye', 'Ver ficha') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
