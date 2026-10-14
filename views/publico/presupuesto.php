<?php
/**
 * @var array<string, mixed> $orden
 * @var string $numero
 * @var list<array<string, mixed>> $items
 * @var float $total
 * @var array<string, mixed> $trabajo
 * @var string $token
 * @var bool $admiteRespuesta
 * @var bool $vigente
 * @var bool $portal
 * @var bool $provistosCliente  ¿hay repuestos que trajo el cliente? (se muestra el aviso de garantía)
 */
?>
<div class="card">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h2 class="h4 mb-1">Presupuesto N° <?= e($numero) ?></h2>
        <div class="text-muted"><?= e(equipo_texto($orden)) ?></div>
      </div>
      <div class="text-end small text-muted">
        Fecha: <?= format_date($orden['created_at']) ?><br>
        Validez: <?= (int) $trabajo['validez'] ?> días <?= $vigente ? '' : '<span class="badge bg-secondary">vencido</span>' ?>
      </div>
    </div>

    <?php if ($orden['falla_reportada']): ?>
      <p><strong>Falla reportada:</strong> <?= nl2br(e($orden['falla_reportada'])) ?></p>
    <?php endif; ?>
    <?php if ($orden['diagnostico']): ?>
      <p><strong>Diagnóstico:</strong> <?= nl2br(e($orden['diagnostico'])) ?></p>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Descripción</th><th class="text-end">Cant.</th><th class="text-end">Precio unit.</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e(item_orden($item)) ?></td>
              <td class="text-end"><?= qty($item['cantidad']) ?></td>
              <td class="text-end"><?= $item['provisto_cliente'] ? '—' : importe($item['precio_unitario']) ?></td>
              <td class="text-end"><?= $item['provisto_cliente'] ? '—' : importe($item['costo']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= importe($total) ?></th></tr></tfoot>
      </table>
    </div>
    <?php if ($provistosCliente): ?>
      <div class="alert alert-light border small">
        <?= icono('box-seam') ?> <strong>Repuestos que trajiste:</strong>
        <?= e(implode(', ', array_map(
          fn($i) => ($i['repuesto_nombre'] ?? $i['descripcion']) . ((float) $i['cantidad'] != 1 ? ' (×' . qty($i['cantidad']) . ')' : ''),
          array_filter($items, fn($i) => (bool) $i['provisto_cliente']),
        ))) ?>.
        No se cobran en este presupuesto.
        <?php if ($trabajo['garantia_repuestos_cliente'] !== ''): ?><div class="mt-1 text-muted"><?= e($trabajo['garantia_repuestos_cliente']) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($orden['presupuesto_respuesta'] === 'aceptado'): ?>
      <p class="text-success fs-5"><?= icono('check-circle-fill') ?> Aceptaste este presupuesto el <?= format_date($orden['presupuesto_respuesta_en']) ?>.</p>
    <?php elseif ($orden['presupuesto_respuesta'] === 'rechazado' && !$admiteRespuesta): ?>
      <p class="text-muted">No aceptaste este presupuesto.</p>
    <?php endif; ?>

    <?php if ($admiteRespuesta): ?>
      <?php if ($orden['presupuesto_respuesta'] === 'rechazado'): ?>
        <p class="text-muted">Habías indicado que no lo aceptás. Si cambiaste de opinión, todavía podés aceptarlo.</p>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <form action="<?= url("presupuesto/{$token}") ?>" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="aceptar">
          <button type="submit" class="btn btn-success btn-lg"><?= icono('check-lg') ?> Acepto el presupuesto</button>
        </form>
        <?php if ($orden['presupuesto_respuesta'] !== 'rechazado'): ?>
          <form action="<?= url("presupuesto/{$token}") ?>" method="POST" data-confirm="¿Seguro que no aceptás el presupuesto?" data-confirm-aceptar="No acepto el presupuesto">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="rechazar">
            <button type="submit" class="btn btn-outline-danger btn-lg">No lo acepto</button>
          </form>
        <?php endif; ?>
      </div>
    <?php elseif (!$vigente && $orden['presupuesto_respuesta'] !== 'aceptado'): ?>
      <p class="text-muted">Este presupuesto venció. Comunicate con nosotros para actualizarlo.</p>
    <?php endif; ?>

    <hr>
    <a href="<?= url("presupuesto/{$token}/pdf") ?>" target="_blank" rel="noopener">Ver en PDF</a>
    <?php if ($portal): ?>
      · <a href="<?= url("presupuesto/{$token}/seguimiento") ?>">Ver el estado de mi equipo</a>
    <?php endif; ?>
  </div>
</div>
