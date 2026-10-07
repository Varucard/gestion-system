<?php
/** @var list<array<string, mixed>> $ordenes */
use App\Enums\EstadoOrden;

?>
<?php if ($ordenes === []): ?>
  <?= $view->partial('componentes/vacio', ['icono' => 'clipboard', 'texto' => 'Sin órdenes registradas.']) ?>
<?php else: ?>
  <?php // En el celular se ocultan Trabajos, Saldo y (en pantallas muy angostas) Fecha: están en la ficha de cada orden. ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>N°</th><th class="d-none d-sm-table-cell">Fecha</th><th>Equipo</th><th class="d-none d-md-table-cell">Trabajos</th><th>Estado</th><th class="text-end">Total</th><th class="text-end d-none d-md-table-cell">Saldo</th></tr>
      </thead>
      <tbody>
        <?php foreach ($ordenes as $o): ?>
          <?php $estado = EstadoOrden::from($o['estado']); ?>
          <tr>
            <td><a href="<?= url("ordenes/{$o['id']}") ?>">#<?= (int) $o['id'] ?></a></td>
            <td class="d-none d-sm-table-cell"><?= format_date($o['created_at']) ?></td>
            <td><?= e($o['equipo']) ?></td>
            <td class="small d-none d-md-table-cell"><?= e(trim(($o['servicios'] ?? '') . ($o['repuestos'] ? ' · ' . $o['repuestos'] : ''), ' ·')) ?></td>
            <td><?= $view->partial('componentes/estado', ['estado' => $estado]) ?></td>
            <td class="text-end"><?= importe($o['total']) ?></td>
            <td class="text-end d-none d-md-table-cell <?= (float) $o['saldo'] > 0 && $estado !== EstadoOrden::Cancelado ? 'text-danger' : '' ?>">
              <?= $estado === EstadoOrden::Cancelado ? '—' : importe($o['saldo']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
