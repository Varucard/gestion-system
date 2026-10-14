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
// Con un solo equipo no hay nada que elegir (p. ej., al volver de cargar un cliente nuevo).
if ($equipoId === 0 && count($equipos) === 1) {
  $equipoId = (int) $equipos[0]['id'];
}
$view->script('turnos.js');
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><?= $turno ? "Editar turno #{$turno['id']}" : 'Nuevo turno' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($turno ? "turnos/{$turno['id']}" : 'turnos') ?>" method="POST" id="form_turno" data-borrador
      data-equipos-url="<?= e(url('clientes/{id}/equipos')) ?>" data-horarios-url="<?= e(url('turnos/horarios')) ?>">
      <?= csrf_field() ?>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="cliente_id" class="form-label">Cliente *</label>
          <select name="cliente_id" id="cliente_id" class="form-select js-buscable<?= error_campo('cliente_id') !== null ? ' is-invalid' : '' ?>" data-placeholder="Escribí el apellido o el DNI" required>
            <option value=""></option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= selected($clienteId === (int) $c['id']) ?>>
                <?= e("{$c['apellido']}, {$c['nombre']} · DNI {$c['dni']}") ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= $view->partial('componentes/error_campo', ['nombre' => 'cliente_id']) ?>
          <?php if (!$turno): ?>
            <a href="<?= url('clientes/crear?para=turno') ?>" class="btn btn-sm btn-outline-secondary mt-2"><?= icono('person-plus') ?> Es un cliente nuevo</a>
            <small class="form-text text-muted d-block">Cargás el cliente y su equipo, y volvés acá con todo elegido.</small>
          <?php endif; ?>
        </div>

        <div class="col-md-6 mb-3">
          <label for="equipo_id" class="form-label">Equipo *</label>
          <select name="equipo_id" id="equipo_id" class="form-select js-buscable<?= error_campo('equipo_id') !== null ? ' is-invalid' : '' ?>" data-placeholder="Primero elegí el cliente"
            required <?= $equipos === [] ? 'disabled' : '' ?>>
            <option value=""></option>
            <?php foreach ($equipos as $eq): ?>
              <option value="<?= (int) $eq['id'] ?>" <?= selected($equipoId === (int) $eq['id']) ?>>
                <?= e(equipo_texto($eq)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= $view->partial('componentes/error_campo', ['nombre' => 'equipo_id']) ?>
        </div>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'fecha', 'etiqueta' => 'Día *', 'tipo' => 'date', 'valor' => $turno['fecha'] ?? $sugerido['fecha'] ?? date('Y-m-d'), 'columna' => 'col-md-4 mb-3',
          'atributos' => ['required' => true, 'min' => $turno ? null : date('Y-m-d')],
        ]) ?>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'hora', 'etiqueta' => 'Hora *', 'tipo' => 'time', 'valor' => substr((string) ($turno['hora'] ?? $sugerido['hora'] ?? ''), 0, 5), 'columna' => 'col-md-4 mb-3',
          'atributos' => ['required' => true, 'data-error' => 'Elegí un horario de la lista o escribí la hora.'],
        ]) ?>

        <?php // Al agendar, el turno siempre nace pendiente: el estado solo se toca al editar. ?>
        <?php if ($turno): ?>
          <?= $view->partial('componentes/campo', [
            'nombre' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select', 'valor' => $turno['estado'], 'columna' => 'col-md-4 mb-3',
            'opciones' => array_combine(
              array_map(fn($opcion) => $opcion->value, $estados),
              array_map(fn($opcion) => $opcion->label(), $estados),
            ),
          ]) ?>
        <?php endif; ?>

        <div class="col-12 mb-3 horarios-libres" id="horarios_libres" aria-live="polite" hidden>
          <div class="small text-muted mb-1 js-horarios-titulo">Horarios de ese día (tocá uno):</div>
          <div class="d-flex flex-wrap gap-2 js-horarios"></div>
        </div>

        <?= $view->partial('componentes/campo', [
          'nombre' => 'descripcion', 'etiqueta' => 'Descripción / falla del equipo', 'tipo' => 'textarea', 'valor' => $turno['descripcion'] ?? '', 'columna' => 'col-12 mb-3',
          'atributos' => ['rows' => 3, 'placeholder' => 'Ej: no enciende, está muy lenta, limpieza y cambio de pasta térmica...'],
        ]) ?>
      </div>

      <p class="small text-muted">Horario de atención: <?= e($horario->resumen()) ?></p>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $turno ? 'Guardar cambios' : 'Guardar turno' ?></button>
      <a href="<?= url('turnos') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </form>
  </div>
</div>
