<?php
/** @var list<array<string, mixed>> $turnos */
use App\Enums\EstadoTurno;
?>
<?php if ($turnos === []): ?>
  <?= $view->partial('componentes/vacio', ['icono' => 'calendar3', 'texto' => 'Sin turnos registrados.']) ?>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>Fecha</th><th>Hora</th><th>Equipo</th><th class="d-none d-md-table-cell">Motivo</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($turnos as $t): ?>
          <tr>
            <td><a href="<?= url("turnos/{$t['id']}/editar") ?>"><?= format_date($t['fecha']) ?></a></td>
            <td><?= e(substr($t['hora'], 0, 5)) ?></td>
            <td><?= e($t['equipo']) ?></td>
            <td class="small d-none d-md-table-cell"><?= e($t['descripcion'] ?? '') ?></td>
            <td><?= e(EstadoTurno::from($t['estado'])->label()) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
