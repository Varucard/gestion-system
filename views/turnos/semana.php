<?php
/** @var array<string, mixed> $agenda (ver AgendaService::semana) */
$hoy = date('Y-m-d');
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0">Semana del <?= format_date($agenda['desde']) ?> al <?= format_date($agenda['hasta']) ?></h4>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('turnos/semana?desde=' . $agenda['anterior']) ?>">← Anterior</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('turnos/semana') ?>">Esta semana</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('turnos/semana?desde=' . $agenda['siguiente']) ?>">Siguiente →</a>
    </div>
  </div>
  <div class="card-body">
    <?php if ($agenda['franjas'] === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'calendar-x', 'texto' => 'No hay días de atención configurados.']) ?>
    <?php else: ?>
      <p class="small text-muted d-md-none mb-2"><?= icono('arrow-left-right') ?> Deslizá la grilla de costado para ver los otros días.</p>
      <div class="table-responsive js-agenda-scroll">
        <table class="table table-bordered table-sm agenda-semanal align-top">
          <thead>
            <tr>
              <th class="ancho-70">Hora</th>
              <?php foreach ($agenda['dias'] as $dia): ?>
                <th class="<?= $dia['fecha'] === $hoy ? 'agenda-hoy' : '' ?> <?= $dia['abierto'] ? '' : 'text-muted' ?>" <?= $dia['fecha'] === $hoy ? 'aria-current="date"' : '' ?>>
                  <?= e($dia['nombre']) ?> <?= format_date($dia['fecha'], 'd/m') ?>
                  <?= $dia['fecha'] === $hoy ? '<span class="badge agenda-hoy-badge">Hoy</span>' : '' ?>
                  <?= $dia['feriado'] ? '<span class="badge bg-secondary">feriado</span>' : '' ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($agenda['franjas'] as $hora): ?>
              <tr>
                <th class="text-nowrap"><?= e($hora) ?></th>
                <?php foreach ($agenda['dias'] as $dia): ?>
                  <?php $celda = $agenda['celdas'][$dia['fecha']][$hora]; ?>
                  <td class="agenda-celda <?= $celda['abierta'] ? '' : 'bg-body-secondary' ?> <?= $dia['fecha'] === $hoy && $celda['abierta'] ? 'agenda-hoy-columna' : '' ?>">
                    <?php foreach ($celda['turnos'] as $t): ?>
                      <a href="<?= url("turnos/{$t['id']}/editar") ?>" class="d-block small rounded px-1 mb-1 text-decoration-none
                        <?= $t['estado'] === 'confirmado' ? 'bg-success-subtle text-success-emphasis' : ($t['estado'] === 'realizado' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-warning-subtle text-warning-emphasis') ?>"
                        title="<?= e($t['cliente'] . ' · ' . $t['equipo'] . ($t['descripcion'] ? ' · ' . $t['descripcion'] : '')) ?>">
                        <strong><?= e(substr($t['hora'], 0, 5)) ?></strong> <?= e("{$t['marca']} {$t['modelo']}") ?>
                        <span class="d-block text-truncate"><?= e($t['cliente']) ?></span>
                      </a>
                    <?php endforeach; ?>
                    <?php if ($celda['abierta'] && !$celda['pasada'] && $celda['libres'] > 0): ?>
                      <a href="<?= url("turnos/crear?fecha={$dia['fecha']}&hora={$hora}") ?>" class="agenda-libre">+ libre<?= $celda['libres'] > 1 ? " ({$celda['libres']})" : '' ?></a>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <script>
        // En el celular la semana no entra: arranca mostrando el día de hoy.
        document.querySelector('.js-agenda-scroll th.agenda-hoy')?.scrollIntoView({ block: 'nearest', inline: 'center' });
      </script>
      <p class="small text-muted mb-0">
        <span class="badge bg-warning-subtle text-warning-emphasis">Pendiente</span>
        <span class="badge bg-success-subtle text-success-emphasis">Confirmado</span>
        <span class="badge bg-primary-subtle text-primary-emphasis">Realizado</span>
        · Tocá “+ libre” para agendar en ese horario.
      </p>
    <?php endif; ?>
  </div>
</div>
