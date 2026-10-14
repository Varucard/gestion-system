<?php
/**
 * @var string $seccion
 * @var array<string, string> $pestanas
 * @var array<string, array<string, mixed>> $config
 * @var array<string, mixed> $extra
 */
?>
<ul class="nav nav-tabs mt-3">
  <?php foreach ($pestanas as $clave => $etiqueta): ?>
    <li class="nav-item">
      <a class="nav-link <?= $clave === $seccion ? 'active' : '' ?>" href="<?= url("configuracion/{$clave}") ?>"><?= e($etiqueta) ?></a>
    </li>
  <?php endforeach; ?>
  <?php // Pantallas de configuración que no son una sección del archivo de configuración. ?>
  <li class="nav-item"><a class="nav-link" href="<?= url('mostrador') ?>">Modo mostrador</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('primeros-pasos') ?>">Primeros pasos</a></li>
</ul>

<div class="card border-top-0 rounded-top-0">
  <div class="card-body">
    <form action="<?= url("configuracion/{$seccion}") ?>" method="POST">
      <?= csrf_field() ?>
      <?= $view->partial("configuracion/_{$seccion}", ['valores' => $config[$seccion], 'config' => $config, ...$extra]) ?>
      <div class="mt-3">
        <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<?php if ($seccion === 'turnos'): ?>
  <div class="card mt-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
      <form action="<?= url('configuracion/feriados/importar') ?>" method="POST" class="d-flex align-items-end gap-2">
        <?= csrf_field() ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'anio', 'etiqueta' => 'Importar feriados nacionales de Argentina', 'tipo' => 'number', 'columna' => 'mb-0',
          'clase' => 'ancho-120', 'valor' => date('Y'), 'usarAnterior' => false, 'atributos' => ['min' => 2020, 'max' => 2100],
        ]) ?>
        <button type="submit" class="btn btn-outline-primary"><?= icono('cloud-download') ?> Importar</button>
      </form>
      <small class="text-muted">Fuente: ArgentinaDatos. Se agregan a la lista de feriados sin borrar los que ya cargaste.</small>
    </div>
  </div>
<?php endif; ?>

<?php if ($seccion === 'backups'): ?>
  <div class="card mt-3">
    <div class="card-body">
      <form action="<?= url('configuracion/backups/generar') ?>" method="POST" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-primary"><?= icono('archive') ?> Generar backup ahora</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($seccion === 'notificaciones'): ?>
  <div class="card mt-3">
    <div class="card-header"><strong>Últimos avisos enviados</strong></div>
    <div class="card-body table-responsive">
      <?php if ($extra['registro'] === []): ?>
        <?= $view->partial('componentes/vacio', ['icono' => 'envelope', 'texto' => 'Todavía no se envió ningún aviso.']) ?>
      <?php else: ?>
        <table class="table table-sm align-middle">
          <?php $nombres = \App\Services\NotificacionService::NOMBRES; $canales = ['email' => 'Email', 'whatsapp_manual' => 'WhatsApp']; ?>
          <thead><tr><th>Fecha</th><th>Cliente</th><th class="d-none d-md-table-cell">Turno</th><th>Aviso</th><th>Por</th><th class="d-none d-md-table-cell">A</th><th>Resultado</th></tr></thead>
          <tbody>
            <?php foreach ($extra['registro'] as $n): ?>
              <?php $manual = $n['canal'] === 'whatsapp_manual'; ?>
              <tr>
                <td><?= format_date($n['created_at'], 'd/m/Y H:i') ?></td>
                <td><?= e($n['cliente'] ?? '—') ?></td>
                <td class="d-none d-md-table-cell"><?= $n['turno_fecha'] ? format_date($n['turno_fecha']) . ' ' . e(substr($n['turno_hora'], 0, 5)) : '—' ?></td>
                <td><?= e($nombres[$n['tipo']] ?? $n['tipo']) ?></td>
                <td><?= e($canales[$n['canal']] ?? $n['canal']) ?></td>
                <td class="d-none d-md-table-cell"><?= e($n['destino']) ?></td>
                <td>
                  <?php // Por WhatsApp no sabemos si se envió: se abrió la conversación con el mensaje. ?>
                  <?php if ($manual): ?>
                    <span class="badge text-bg-secondary">abierto</span>
                  <?php else: ?>
                    <span class="badge bg-<?= $n['estado'] === 'enviado' ? 'success' : 'danger' ?>"><?= $n['estado'] === 'enviado' ? 'enviado' : 'no salió' ?></span>
                  <?php endif; ?>
                  <?php if ($n['detalle']): ?><div class="small text-muted"><?= e($n['detalle']) ?></div><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
