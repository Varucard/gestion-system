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
        <tr><th>N°</th><th class="d-none d-sm-table-cell">Fecha</th><th>Equipo</th><th class="d-none d-md-table-cell">Trabajos</th><th>Estado</th><th class="text-end">Total</th><th class="text-end d-none d-md-table-cell">Saldo</th><th><span class="visually-hidden">Acción</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($ordenes as $o): ?>
          <?php
          $estado = EstadoOrden::from($o['estado']);
          $aCobrar = $estado === EstadoOrden::Finalizado && (float) $o['saldo'] > 0;
          ?>
          <?php // Toda la fila lleva a la orden (data-href, ver app.js): el "#4" solo era muy chico para el dedo. ?>
          <tr class="fila-link" data-href="<?= url("ordenes/{$o['id']}") ?>">
            <td><a href="<?= url("ordenes/{$o['id']}") ?>" class="fw-semibold">#<?= (int) $o['id'] ?></a></td>
            <td class="d-none d-sm-table-cell"><?= format_date($o['created_at']) ?></td>
            <td><?= e($o['equipo']) ?></td>
            <td class="small d-none d-md-table-cell"><?= e(trim(($o['servicios'] ?? '') . ($o['repuestos'] ? ' · ' . $o['repuestos'] : '') . ($o['repuestos_cliente'] ? ' · Trajo el cliente: ' . $o['repuestos_cliente'] : ''), ' ·')) ?></td>
            <td><?= $view->partial('componentes/estado', ['estado' => $estado]) ?></td>
            <td class="text-end"><?= importe($o['total']) ?></td>
            <td class="text-end d-none d-md-table-cell <?= (float) $o['saldo'] > 0 && $estado !== EstadoOrden::Cancelado ? 'text-danger' : '' ?>">
              <?= $estado === EstadoOrden::Cancelado ? '—' : importe($o['saldo']) ?>
            </td>
            <td class="text-end text-nowrap">
              <?php if ($aCobrar): ?>
                <a href="<?= url("ordenes/{$o['id']}#registrar_pago") ?>" class="btn btn-sm btn-seccion"><?= icono('cash-coin') ?> Cobrar</a>
              <?php else: ?>
                <?= boton_accion("ordenes/{$o['id']}", 'eye', 'Ver') ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
