<?php
/**
 * Entregar el equipo: lo que se hizo, lo que falta cobrar y la firma de conformidad.
 *
 * @var array<string, mixed> $orden
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $items
 * @var float $saldo
 */
$id = (int) $orden['id'];
?>
<?php if ($saldo > 0): ?>
  <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center mt-3">
    <span class="me-auto"><?= icono('exclamation-triangle') ?> Falta cobrar <strong><?= importe($saldo) ?></strong>. Si se lo lleva igual, queda como deuda del cliente.</span>
    <a href="<?= url("ordenes/{$id}#registrar_pago") ?>" class="btn btn-outline-secondary"><?= icono('cash-coin') ?> Cobrar primero</a>
  </div>
<?php endif; ?>

<form action="<?= url("ordenes/{$id}/entregar") ?>" method="POST" class="mt-3">
  <?= csrf_field() ?>
  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= e(equipo_texto($orden)) ?></h2>
      <p class="text-muted mb-2"><?= e("{$cliente['apellido']}, {$cliente['nombre']}") ?></p>
      <ul class="mb-2">
        <?php foreach ($items as $item): ?>
          <li><?= e(item_orden($item)) ?><?= (float) $item['cantidad'] !== 1.0 ? ' × ' . qty($item['cantidad']) : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="mb-0">Total: <strong><?= importe($orden['total']) ?></strong><?= $saldo > 0 ? ' · Saldo: <strong class="text-danger">' . importe($saldo) . '</strong>' : ' · Pagado' ?></p>
    </div>
  </div>

  <?= $view->partial('componentes/firma', [
    'texto' => 'El cliente firma que recibe el equipo en conformidad con los trabajos detallados.',
    'aclaracion' => trim("{$cliente['nombre']} {$cliente['apellido']}"),
  ]) ?>
  <p class="small text-muted mt-2">La firma es opcional, pero queda en el comprobante de entrega.</p>

  <div class="d-flex flex-wrap gap-2 mt-3">
    <button type="submit" class="btn btn-success btn-lg"><?= icono('key') ?> Entregar el equipo</button>
    <a href="<?= url("ordenes/{$id}") ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
  </div>
</form>
