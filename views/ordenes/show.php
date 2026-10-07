<?php
/**
 * @var array<string, mixed> $orden
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $pagos
 * @var float $saldo
 * @var list<string> $formasPago
 * @var \App\Enums\EstadoOrden $estado
 * @var list<array<string, mixed>> $historial
 * @var bool $puedeEnviar
 */
use App\Services\OrdenService;

$pagado = (float) $orden['total'] - $saldo;
?>
<div class="d-flex flex-wrap gap-2 mt-3 mb-3">
  <?php if (OrdenService::editable($estado)): ?>
    <a href="<?= url("ordenes/{$orden['id']}/editar") ?>" class="btn btn-outline-primary"><?= icono('pencil') ?> Editar orden</a>
  <?php endif; ?>
  <a href="<?= url("ordenes/{$orden['id']}/presupuesto") ?>" class="btn btn-outline-secondary"><?= icono('file-text') ?> Presupuesto</a>
  <a href="<?= url("ordenes/{$orden['id']}/presupuesto/pdf") ?>" class="btn btn-outline-secondary"><?= icono('file-earmark-pdf') ?> Presupuesto PDF</a>
  <?php if ($puedeEnviar && \App\Services\OrdenService::editable($estado)): ?>
    <form action="<?= url("ordenes/{$orden['id']}/enviar-presupuesto") ?>" method="POST" class="d-inline"
      data-confirm="¿Enviar el presupuesto por email a <?= e($cliente['email'] ?: 'el cliente') ?>?">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline-primary" <?= $cliente['email'] ? '' : 'disabled title="El cliente no tiene email"' ?>><?= icono('envelope') ?> Enviar presupuesto</button>
    </form>
  <?php endif; ?>
  <?php if ($estado->value === 'finalizado'): ?>
    <a href="<?= url("ordenes/{$orden['id']}/entrega") ?>" class="btn btn-seccion"><?= icono('receipt') ?> Comprobante de entrega</a>
    <a href="<?= url("ordenes/{$orden['id']}/entrega/pdf") ?>" class="btn btn-outline-secondary"><?= icono('file-earmark-pdf') ?> Entrega PDF</a>
  <?php endif; ?>
  <a href="<?= url('ordenes') ?>" class="btn btn-outline-secondary ms-auto">Volver al listado</a>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Orden #<?= (int) $orden['id'] ?></strong></div>
      <div class="card-body">
        <p class="mb-1">Estado: <?= $view->partial('componentes/estado', ['estado' => $estado]) ?></p>
        <p class="mb-1">Fecha: <?= format_date($orden['created_at']) ?></p>
        <p class="mb-1">Técnico: <?= e($orden['tecnico'] ?? 'sin asignar') ?></p>
        <?php if ($orden['presupuesto_respuesta'] === 'aceptado'): ?>
          <p class="mb-1 text-success"><?= icono('check-lg') ?> Presupuesto aceptado por el cliente (<?= format_date($orden['presupuesto_respuesta_en'], 'd/m H:i') ?>)</p>
        <?php elseif ($orden['presupuesto_respuesta'] === 'rechazado'): ?>
          <p class="mb-1 text-danger"><?= icono('x-lg') ?> Presupuesto rechazado por el cliente (<?= format_date($orden['presupuesto_respuesta_en'], 'd/m H:i') ?>)</p>
        <?php elseif ($orden['presupuesto_enviado']): ?>
          <p class="mb-1 text-muted">Presupuesto enviado el <?= format_date($orden['presupuesto_enviado'], 'd/m H:i') ?>, sin respuesta</p>
        <?php endif; ?>
        <?php if ($orden['turno_id']): ?><p class="mb-1">Desde el turno <a href="<?= url("turnos/{$orden['turno_id']}/editar") ?>">#<?= (int) $orden['turno_id'] ?></a></p><?php endif; ?>
        <?php if ($orden['fecha_realizado']): ?><p class="mb-1">Finalizada: <?= format_date($orden['fecha_realizado']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Cliente</strong></div>
      <div class="card-body">
        <p class="mb-1"><a href="<?= url("clientes/{$cliente['id']}") ?>"><?= e("{$cliente['apellido']}, {$cliente['nombre']}") ?></a></p>
        <p class="mb-1">Tel: <?= e($cliente['telefono']) ?></p>
        <?php if ($cliente['email']): ?><p class="mb-1"><?= e($cliente['email']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Equipo</strong></div>
      <div class="card-body">
        <p class="mb-1"><a href="<?= url("equipos/{$orden['equipo_id']}") ?>"><?= e(equipo_texto($orden, false)) ?></a></p>
        <?php if ($orden['numero_serie']): ?><p class="mb-1">N° de serie: <?= e($orden['numero_serie']) ?></p><?php endif; ?>
        <?php if ($orden['accesorios']): ?><p class="mb-1">Accesorios: <?= e($orden['accesorios']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($orden['falla_reportada'] || $orden['estado_ingreso'] || $orden['diagnostico'] || $orden['trabajo_realizado'] || $orden['notas_internas'] || $orden['proximo_mantenimiento_fecha']): ?>
  <div class="card mt-3">
    <div class="card-body">
      <div class="row g-3">
        <?php if ($orden['falla_reportada']): ?>
          <div class="col-md-6"><strong>Falla reportada</strong><div><?= nl2br(e($orden['falla_reportada'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['estado_ingreso']): ?>
          <div class="col-md-6"><strong>Estado físico al ingresar</strong><div><?= nl2br(e($orden['estado_ingreso'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['diagnostico']): ?>
          <div class="col-md-6"><strong>Diagnóstico</strong><div><?= nl2br(e($orden['diagnostico'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['trabajo_realizado']): ?>
          <div class="col-md-6"><strong>Trabajo realizado</strong><div><?= nl2br(e($orden['trabajo_realizado'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['proximo_mantenimiento_fecha']): ?>
          <div class="col-md-6">
            <strong>Próximo mantenimiento</strong>
            <div>
              <?= format_date($orden['proximo_mantenimiento_fecha']) ?>
              <?php if ($orden['proximo_mantenimiento_avisado']): ?><span class="small text-muted">(avisado el <?= format_date($orden['proximo_mantenimiento_avisado']) ?>)</span><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($orden['notas_internas']): ?>
          <div class="col-md-6"><strong>Notas internas</strong><div class="text-muted"><?= nl2br(e($orden['notas_internas'])) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card mt-3">
  <div class="card-header"><strong>Detalle</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>Ítem</th><th class="text-end">Cantidad</th><th class="text-end">Precio unit.</th><th class="text-end">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['repuesto_id'] !== null ? 'Repuesto: ' . $item['repuesto_nombre'] : $item['servicio_nombre']) ?></td>
            <td class="text-end"><?= qty($item['cantidad']) ?></td>
            <td class="text-end"><?= importe($item['precio_unitario']) ?></td>
            <td class="text-end"><?= importe($item['costo']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= importe($orden['total']) ?></th></tr>
        <tr><td colspan="3" class="text-end">Pagado</td><td class="text-end"><?= importe($pagado) ?></td></tr>
        <tr class="<?= $saldo > 0 ? 'table-warning' : 'table-success' ?>">
          <th colspan="3" class="text-end">Saldo</th><th class="text-end"><?= importe($saldo) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Pagos</strong></div>
  <div class="card-body">
    <?php if ($pagos === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'cash-coin', 'texto' => 'Todavía no se registraron pagos.']) ?>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr><th>Fecha</th><th>Forma de pago</th><th class="d-none d-md-table-cell">Observación</th><th class="d-none d-md-table-cell">Registró</th><th class="text-end">Monto</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <tr>
                <td><?= format_date($p['fecha']) ?></td>
                <td><?= e($p['forma_pago']) ?></td>
                <td class="d-none d-md-table-cell"><?= e($p['observacion'] ?? '') ?></td>
                <td class="d-none d-md-table-cell"><?= e($p['usuario'] ?? '—') ?></td>
                <td class="text-end"><?= importe($p['monto']) ?></td>
                <td class="text-end">
                  <?php if (auth()->esAdministrador()): ?>
                    <?= $view->partial('partials/delete_button', [
                      'action' => "pagos/{$p['id']}/anular",
                      'label' => 'Anular',
                      'icono' => 'x-circle',
                      'confirm' => '¿Anular el pago de $ ' . money($p['monto']) . '?',
                    ]) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($saldo > 0 && $estado->value !== 'cancelado'): ?>
      <h6 class="mt-3">Registrar pago</h6>
      <form action="<?= url("ordenes/{$orden['id']}/pagos") ?>" method="POST" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-sm-3">
          <label for="monto" class="form-label">Monto *</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" max="<?= $saldo ?>" required
              value="<?= e(old('monto', number_format($saldo, 2, '.', ''))) ?>">
          </div>
        </div>
        <div class="col-sm-3">
          <label for="forma_pago" class="form-label">Forma de pago *</label>
          <select class="form-select" id="forma_pago" name="forma_pago" required>
            <?php foreach ($formasPago as $forma): ?>
              <option <?= selected($forma === old('forma_pago')) ?>><?= e($forma) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-2">
          <label for="fecha" class="form-label">Fecha *</label>
          <input type="date" class="form-control" id="fecha" name="fecha" max="<?= date('Y-m-d') ?>" required value="<?= e(old('fecha', date('Y-m-d'))) ?>">
        </div>
        <div class="col-sm-4">
          <label for="observacion" class="form-label">Observación</label>
          <input type="text" class="form-control" id="observacion" name="observacion" maxlength="255" value="<?= e(old('observacion')) ?>">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> Registrar pago</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Historial</strong></div>
  <div class="card-body">
    <?php if ($historial === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'clock-history', 'texto' => 'Sin movimientos registrados.']) ?>
    <?php else: ?>
      <ul class="list-unstyled mb-0 small">
        <?php foreach ($historial as $h): ?>
          <li class="mb-1">
            <span class="text-muted"><?= format_date($h['created_at'], 'd/m/Y H:i') ?></span> ·
            <strong><?= e($h['usuario_nombre'] ?? '—') ?></strong> · <?= e($h['descripcion']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
