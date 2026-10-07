<?php
/**
 * @var array<string, mixed>|null $turno
 * @var list<array<string, mixed>> $clientes
 * @var list<array<string, mixed>> $equipos  equipos del cliente seleccionado
 * @var list<\App\Enums\EstadoTurno> $estados
 * @var \App\Support\HorarioAtencion $horario
 */
$clienteId = (int) old('cliente_id', $turno['cliente_id'] ?? $clienteSugerido);
$equipoId = (int) old('equipo_id', $turno['equipo_id'] ?? 0);
$view->script('turnos.js');
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><?= $turno ? "Editar turno #{$turno['id']}" : 'Agendar nuevo turno' ?></h4>
    <a href="<?= url('turnos') ?>" class="btn btn-outline-secondary">Volver a la agenda</a>
  </div>
  <div class="card-body">
    <form action="<?= url($turno ? "turnos/{$turno['id']}" : 'turnos') ?>" method="POST" id="form_turno"
      data-equipos-url="<?= e(url('clientes/{id}/equipos')) ?>">
      <?= csrf_field() ?>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="cliente_id" class="form-label">Cliente *</label>
          <select name="cliente_id" id="cliente_id" class="form-select js-buscable" data-placeholder="Seleccione un cliente" required>
            <option value=""></option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= selected($clienteId === (int) $c['id']) ?>>
                <?= e("{$c['apellido']}, {$c['nombre']}") ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6 mb-3">
          <label for="equipo_id" class="form-label">Equipo *</label>
          <select name="equipo_id" id="equipo_id" class="form-select js-buscable" data-placeholder="Seleccione primero un cliente"
            required <?= $equipos === [] ? 'disabled' : '' ?>>
            <option value=""></option>
            <?php foreach ($equipos as $eq): ?>
              <option value="<?= (int) $eq['id'] ?>" <?= selected($equipoId === (int) $eq['id']) ?>>
                <?= e(equipo_texto($eq)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'fecha', 'etiqueta' => 'Fecha *', 'tipo' => 'date', 'valor' => $turno['fecha'] ?? $sugerido['fecha'] ?? date('Y-m-d'), 'columna' => 'col-md-4 mb-3',
          'atributos' => ['required' => true, 'min' => $turno ? null : date('Y-m-d')],
        ]) ?>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'hora', 'etiqueta' => 'Hora *', 'tipo' => 'time', 'valor' => substr((string) ($turno['hora'] ?? $sugerido['hora'] ?? ''), 0, 5), 'columna' => 'col-md-4 mb-3',
          'atributos' => ['required' => true],
        ]) ?>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select', 'valor' => $turno['estado'] ?? 'pendiente', 'columna' => 'col-md-4 mb-3',
          'opciones' => array_combine(
            array_map(fn($opcion) => $opcion->value, $estados),
            array_map(fn($opcion) => $opcion->label(), $estados),
          ),
        ]) ?>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'descripcion', 'etiqueta' => 'Descripción / falla del equipo', 'tipo' => 'textarea', 'valor' => $turno['descripcion'] ?? '', 'columna' => 'col-12 mb-3',
          'atributos' => ['rows' => 3, 'placeholder' => 'Ej: no enciende, está muy lenta, limpieza y cambio de pasta térmica...'],
        ]) ?>
      </div>

      <p class="small text-muted">Horario de atención: <?= e($horario->resumen()) ?></p>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $turno ? 'Guardar cambios' : 'Confirmar turno' ?></button>
      <a href="<?= url('turnos') ?>" class="btn btn-outline-secondary ms-2">Cancelar</a>
    </form>
  </div>
</div>
