<?php
/**
 * @var list<array<string, mixed>> $turnosHoy
 * @var list<array<string, mixed>> $turnosManana
 * @var array{pendiente: int, en_proceso: int} $abiertas
 * @var list<array<string, mixed>> $ordenesAbiertas
 * @var float $cobradoMes
 * @var int $finalizadasMes
 * @var list<array<string, mixed>> $deudores
 * @var float $totalAdeudado
 * @var list<array<string, mixed>> $stockBajo
 * @var list<array<string, mixed>> $mantenimientos
 * @var array{canal: bool, whatsapp: bool} $avisos
 */
use App\Enums\EstadoTurno;

$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$tarjetas = [
  // El valor ya es HTML seguro: un número o un importe().
  ['Turnos de hoy', (string) count($turnosHoy), 'turnos', 'warning'],
  ['Órdenes abiertas', (string) ($abiertas['pendiente'] + $abiertas['en_proceso']), 'ordenes', 'primary'],
  ['Cobrado en ' . $meses[(int) date('n')], importe($cobradoMes), 'ordenes', 'success'],
  ['Saldo adeudado', importe($totalAdeudado), 'deudores', 'danger'],
];
?>
<div class="row g-3 mt-1">
  <?php foreach ($tarjetas as [$etiqueta, $valor, $link, $color]): ?>
    <div class="col-6 col-lg-3">
      <a href="<?= url($link) ?>" class="card text-decoration-none border-<?= $color ?> h-100">
        <div class="card-body">
          <div class="small text-muted"><?= e($etiqueta) ?></div>
          <div class="fs-3 fw-semibold text-<?= $color ?>"><?= $valor ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Turnos de hoy</strong>
        <a href="<?= url('turnos/crear') ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> Agendar</a>
      </div>
      <div class="card-body">
        <?php if ($turnosHoy === []): ?>
          <?= $view->partial('componentes/vacio', ['icono' => 'calendar-x', 'texto' => 'No hay turnos para hoy.']) ?>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($turnosHoy as $t): ?>
              <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                <div>
                  <strong><?= e(substr($t['hora'], 0, 5)) ?></strong> · <a href="<?= url("clientes/{$t['cliente_id']}") ?>"><?= e($t['cliente']) ?></a>
                  <div class="small text-muted"><?= e($t['equipo']) ?><?= $t['descripcion'] ? ' · ' . e($t['descripcion']) : '' ?></div>
                </div>
                <span class="badge bg-secondary"><?= e(EstadoTurno::from($t['estado'])->label()) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($turnosManana !== []): ?>
          <h6 class="mt-3">Mañana · enviar recordatorios</h6>
          <ul class="list-group list-group-flush">
            <?php foreach ($turnosManana as $t): ?>
              <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                  <strong><?= e(substr($t['hora'], 0, 5)) ?></strong> · <?= e($t['cliente']) ?>
                  <div class="small text-muted"><?= e($t['equipo']) ?></div>
                </div>
                <div class="text-end">
                  <?= $view->partial('turnos/_recordatorio', ['turno' => $t, 'avisos' => $avisos, 'volver' => 'inicio']) ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Órdenes abiertas</strong>
        <span class="small text-muted"><?= $abiertas['pendiente'] ?> pendientes · <?= $abiertas['en_proceso'] ?> en proceso</span>
      </div>
      <div class="card-body">
        <?php if ($ordenesAbiertas === []): ?>
          <?= $view->partial('componentes/vacio', ['icono' => 'clipboard-check', 'texto' => 'No hay órdenes abiertas.']) ?>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($ordenesAbiertas as $o): ?>
              <li class="list-group-item px-0">
                <a href="<?= url("ordenes/{$o['id']}") ?>">#<?= (int) $o['id'] ?></a> · <?= e($o['equipo']) ?>
                <div class="small text-muted"><?= e($o['cliente']) ?><?= $o['tecnico'] ? ' · ' . icono('wrench') . ' ' . e($o['tecnico']) : '' ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p class="small text-muted mt-2 mb-0">Finalizadas este mes: <?= $finalizadasMes ?></p>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Mayores saldos pendientes</strong>
        <a href="<?= url('deudores') ?>" class="small">Ver todos</a>
      </div>
      <div class="card-body">
        <?php if ($deudores === []): ?>
          <?= $view->partial('componentes/vacio', ['icono' => 'emoji-smile', 'texto' => 'No hay clientes con deuda.']) ?>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($deudores as $d): ?>
              <li class="list-group-item px-0 d-flex justify-content-between">
                <a href="<?= url("clientes/{$d['id']}") ?>"><?= e($d['cliente']) ?></a>
                <span class="text-danger"><?= importe($d['saldo']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Stock bajo mínimo</strong>
        <a href="<?= url('repuestos') ?>" class="small">Ver repuestos</a>
      </div>
      <div class="card-body">
        <?php if ($stockBajo === []): ?>
          <p class="text-muted mb-0">Todo el stock está por encima del mínimo.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($stockBajo as $r): ?>
              <li class="list-group-item px-0 d-flex justify-content-between">
                <a href="<?= url("repuestos/{$r['id']}/stock") ?>"><?= e($r['nombre']) ?></a>
                <span><span class="text-danger"><?= qty($r['stock_actual']) ?></span> <small class="text-muted">/ mín. <?= qty($r['stock_minimo']) ?><?= $r['proveedor'] ? ' · ' . e($r['proveedor']) : '' ?></small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($mantenimientos !== []): ?>
  <div class="card mt-3">
    <div class="card-header"><strong>Mantenimientos a vencer en los próximos 30 días</strong></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($mantenimientos as $m): ?>
        <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
          <span>
            <strong><?= format_date($m['proximo_mantenimiento_fecha']) ?></strong> ·
            <a href="<?= url("equipos/{$m['equipo_id']}") ?>"><?= e(equipo_texto($m)) ?></a> · <?= e($m['cliente']) ?>
          </span>
          <span class="small <?= $m['proximo_mantenimiento_avisado'] ? 'text-success' : 'text-muted' ?>">
            <?= $m['proximo_mantenimiento_avisado'] ? icono('check-lg') . ' avisado el ' . format_date($m['proximo_mantenimiento_avisado']) : 'sin avisar' ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
