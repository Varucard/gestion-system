<?php
/**
 * @var list<string> $fechas
 * @var string $fecha
 * @var string $nivel
 * @var string $buscar
 * @var list<array<string, mixed>> $eventos
 */
use App\Core\Logger;

$colores = ['debug' => 'secondary', 'info' => 'info', 'notice' => 'primary', 'warning' => 'warning', 'error' => 'danger', 'critical' => 'danger'];
?>
<div class="card mt-3">
  <div class="card-header"><h4 class="mb-0">Registro técnico del sistema</h4></div>
  <div class="card-body">
    <form method="GET" action="<?= url('logs') ?>" class="row g-2 mb-3 align-items-end">
      <div class="col-sm-3">
        <label for="fecha" class="form-label small">Día</label>
        <select id="fecha" name="fecha" class="form-select form-select-sm">
          <?php foreach ($fechas ?: [$fecha] as $f): ?>
            <option value="<?= e($f) ?>" <?= selected($f === $fecha) ?>><?= format_date($f) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label for="nivel" class="form-label small">Nivel mínimo</label>
        <select id="nivel" name="nivel" class="form-select form-select-sm">
          <?php foreach (array_keys(Logger::NIVELES) as $n): ?>
            <option value="<?= e($n) ?>" <?= selected($n === $nivel) ?>><?= e($n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4">
        <label for="buscar" class="form-label small">Buscar (texto, código de error, usuario…)</label>
        <input type="text" id="buscar" name="buscar" class="form-control form-control-sm" value="<?= e($buscar) ?>">
      </div>
      <div class="col-sm-2">
        <button type="submit" class="btn btn-sm btn-primary w-100">Ver</button>
      </div>
    </form>

    <?php if ($eventos === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'journal-x', 'texto' => 'No hay eventos para esos filtros.']) ?>
    <?php else: ?>
      <p class="small text-muted">Mostrando los últimos <?= count($eventos) ?> eventos (máximo 500).</p>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Hora</th><th>Nivel</th><th>Mensaje</th><th>Petición</th></tr></thead>
          <tbody>
            <?php foreach ($eventos as $ev): ?>
              <tr>
                <td class="text-nowrap"><?= e(substr((string) $ev['fecha'], 11, 8)) ?></td>
                <td><span class="badge bg-<?= $colores[$ev['nivel']] ?? 'secondary' ?>"><?= e($ev['nivel']) ?></span></td>
                <td>
                  <?= e($ev['mensaje']) ?>
                  <?php if (!empty($ev['excepcion'])): ?>
                    <details class="small"><summary><?= e($ev['excepcion']['clase']) ?> en <?= e($ev['excepcion']['archivo']) ?></summary><pre class="mb-0 pre-ajustado"><?= e($ev['excepcion']['traza']) ?></pre></details>
                  <?php endif; ?>
                  <?php if (!empty($ev['contexto'])): ?>
                    <details class="small"><summary>contexto</summary><pre class="mb-0 pre-ajustado"><?= e(json_encode($ev['contexto'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <code><?= e($ev['request_id'] ?? '') ?></code><br>
                  <?= e($ev['usuario'] ?? '') ?> <?= e($ev['ip'] ?? '') ?><br><?= e($ev['ruta'] ?? ($ev['canal'] ?? '')) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
