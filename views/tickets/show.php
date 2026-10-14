<?php
/**
 * Ticket para impresora térmica: angosto (58 u 80 mm), en blanco y negro y con un QR. Se
 * imprime desde el navegador eligiendo la impresora de tickets.
 *
 * @var string $tipo  recepcion | presupuesto | entrega
 * @var string $titulo
 * @var array<string, mixed> $orden
 * @var string $numero
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $items
 * @var float $total
 * @var float $pagado
 * @var float $saldo
 * @var array<string, mixed> $negocio
 * @var array<string, mixed> $trabajo
 * @var int $ancho  mm del papel
 * @var string $qr  SVG
 * @var string $textoQr
 * @var bool $imprimir
 */
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/png" href="<?= asset('img/logo_64.png') ?>">
  <title>Ticket <?= e($titulo) ?> #<?= e($numero) ?> - <?= e($negocio['nombre']) ?></title>
  <style>
    /* Hoja propia: el ticket se imprime solo, sin el diseño del sistema. */
    @page { size: <?= (int) $ancho ?>mm auto; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; font: 12px/1.35 "DejaVu Sans Mono", Menlo, Consolas, monospace; color: #000; }
    .ticket { width: <?= (int) $ancho ?>mm; margin: 12px auto; padding: 4mm 3mm; background: #fff; }
    h1 { font-size: 15px; margin: 0; text-align: center; }
    .centro { text-align: center; }
    .chico { font-size: 10.5px; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; padding: 1px 0; }
    td.monto { text-align: right; white-space: nowrap; padding-left: 4px; }
    .total td { font-weight: bold; font-size: 13px; }
    .qr svg { display: block; width: <?= $ancho >= 80 ? '38' : '30' ?>mm; height: auto; margin: 4px auto 2px; }
    .acciones { width: <?= (int) $ancho ?>mm; margin: 0 auto 16px; display: flex; gap: 6px; }
    .acciones button, .acciones a { flex: 1; padding: 8px; font: 14px system-ui, sans-serif; text-align: center; border: 1px solid #6c757d; border-radius: 6px; background: #fff; color: #000; text-decoration: none; cursor: pointer; }
    .acciones button { background: #0d6efd; color: #fff; border-color: #0d6efd; }
    @media print {
      body { background: #fff; }
      .ticket { margin: 0; }
      .acciones { display: none; }
    }
  </style>
</head>

<body>
  <div class="ticket">
    <h1><?= e($negocio['nombre']) ?></h1>
    <div class="centro chico"><?= e($negocio['direccion']) ?><br>Tel. <?= e($negocio['telefono']) ?></div>
    <hr>
    <div class="centro"><strong><?= e(mb_strtoupper($titulo)) ?> · ORDEN #<?= e($numero) ?></strong></div>
    <div class="centro chico"><?= date('d/m/Y H:i') ?></div>
    <hr>
    <div><?= e(trim("{$cliente['nombre']} {$cliente['apellido']}")) ?></div>
    <div><?= e(equipo_texto($orden, false)) ?></div>
    <?php if ($orden['numero_serie']): ?><div>S/N: <strong><?= e($orden['numero_serie']) ?></strong></div><?php endif; ?>

    <?php if ($tipo === 'recepcion'): ?>
      <?php if ($orden['falla_reportada']): ?><hr><div class="chico"><strong>Falla:</strong> <?= nl2br(e($orden['falla_reportada'])) ?></div><?php endif; ?>
      <?php if ($orden['accesorios']): ?><div class="chico"><strong>Accesorios:</strong> <?= e($orden['accesorios']) ?></div><?php endif; ?>
      <?php if ($orden['estado_ingreso']): ?><div class="chico"><strong>Cómo llegó:</strong> <?= nl2br(e($orden['estado_ingreso'])) ?></div><?php endif; ?>
    <?php else: ?>
      <hr>
      <table>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e(item_orden($item)) ?><?= (float) $item['cantidad'] !== 1.0 ? ' x' . qty($item['cantidad']) : '' ?></td>
            <td class="monto"><?= $item['provisto_cliente'] ? '—' : '$' . money($item['costo']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total"><td><?= $tipo === 'presupuesto' ? 'TOTAL ESTIMADO' : 'TOTAL' ?></td><td class="monto">$<?= money($total) ?></td></tr>
        <?php if ($tipo === 'entrega'): ?>
          <tr><td>Pagado</td><td class="monto">$<?= money($pagado) ?></td></tr>
          <tr class="total"><td>SALDO</td><td class="monto">$<?= money($saldo) ?></td></tr>
        <?php endif; ?>
      </table>
      <?php if ($tipo === 'presupuesto'): ?>
        <div class="chico">Válido por <?= (int) $trabajo['validez'] ?> días.</div>
      <?php else: ?>
        <div class="chico">Garantía: <?= (int) $trabajo['garantia'] ?> días desde la entrega.</div>
      <?php endif; ?>
    <?php endif; ?>

    <hr>
    <div class="qr" role="img" aria-label="Código QR"><?= $qr ?></div>
    <div class="centro chico"><?= e($textoQr) ?></div>
    <hr>
    <div class="centro chico"><?= e($trabajo['mensaje_legal']) ?></div>
  </div>

  <div class="acciones">
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="<?= url("ordenes/{$orden['id']}") ?>">Volver</a>
  </div>

  <?php if ($imprimir): ?>
    <script>window.addEventListener('load', () => window.print());</script>
  <?php endif; ?>
</body>

</html>
