<?php
/**
 * @var array<string, mixed>|null $orden
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $servicios
 * @var list<array<string, mixed>> $repuestos
 * @var array{servicio: array<int, array<string, mixed>>, repuesto: array<int, array<string, mixed>>} $detalle
 * @var array<string, mixed> $precarga  datos sugeridos al crear (p. ej. desde un turno)
 * @var array<string, mixed> $mantenimiento  configuración del intervalo sugerido
 * @var list<array<string, mixed>> $combos
 */
// Al editar, el técnico asignado debe figurar aunque hoy esté inactivo.
if ($orden && $orden['tecnico_id'] && !in_array((int) $orden['tecnico_id'], array_map('intval', array_column($tecnicos, 'id')), true)) {
  $tecnicos[] = ['id' => $orden['tecnico_id'], 'apellido' => $orden['tecnico'], 'nombre' => null, 'puesto' => 'inactivo'];
}
$view->script('ordenes.js');
?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $orden ? "Editar orden #{$orden['id']}" : 'Nueva orden' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($orden ? "ordenes/{$orden['id']}" : 'ordenes') ?>" method="POST" id="form_orden"
      data-detalle="<?= e(json_encode($detalle, JSON_FORCE_OBJECT)) ?>">
      <?= csrf_field() ?>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'equipo_id', 'etiqueta' => 'Equipo *', 'tipo' => 'select', 'buscable' => true, 'columna' => 'col-md-8',
          'placeholder' => 'Seleccione un equipo', 'valor' => $orden['equipo_id'] ?? $equipoSugerido, 'atributos' => ['required' => true],
          'opciones' => array_column(array_map(fn($eq) => [(int) $eq['id'], "{$eq['cliente']} - " . equipo_texto($eq)], $equipos), 1, 0),
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'tecnico_id', 'etiqueta' => 'Técnico asignado', 'tipo' => 'select', 'buscable' => true, 'columna' => 'col-md-4',
          'placeholder' => 'Sin asignar', 'valor' => $orden['tecnico_id'] ?? '',
          'opciones' => array_column(array_map(fn($m) => [
            (int) $m['id'], trim("{$m['apellido']}" . ($m['nombre'] ? ", {$m['nombre']}" : '') . " ({$m['puesto']})"),
          ], $tecnicos), 1, 0),
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'servicio_id[]', 'id' => 'servicio_id', 'etiqueta' => 'Servicios *', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6', 'clase' => 'js-item-precio', 'placeholder' => 'Seleccione uno o más servicios',
          'atributos' => ['multiple' => true, 'data-tipo' => 'servicio'],
          'valor' => array_keys($detalle['servicio']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($s) => [(int) $s['id'], [
            'texto' => "{$s['nombre']} ($ " . money($s['precio_base']) . ')', 'atributos' => ['data-precio' => (float) $s['precio_base']],
          ]], $servicios), 1, 0),
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'repuesto_id[]', 'id' => 'repuesto_id', 'etiqueta' => 'Repuestos', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6', 'clase' => 'js-item-precio', 'placeholder' => 'Seleccione uno o más repuestos',
          'atributos' => ['multiple' => true, 'data-tipo' => 'repuesto'],
          'valor' => array_keys($detalle['repuesto']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($r) => [(int) $r['id'], [
            'texto' => "{$r['nombre']} ($ " . money($r['precio']) . ') · stock ' . qty($r['stock_actual']), 'atributos' => ['data-precio' => (float) $r['precio']],
          ]], $repuestos), 1, 0),
        ]) ?>
      </div>

      <?php if (!empty($precarga['turno_id'])): ?>
        <input type="hidden" name="turno_id" value="<?= (int) $precarga['turno_id'] ?>">
        <div class="alert alert-info py-2">Esta orden se crea desde un turno: al guardarla, el turno queda como <strong>realizado</strong>.</div>
      <?php endif; ?>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'falla_reportada', 'etiqueta' => 'Falla reportada', 'tipo' => 'textarea', 'columna' => 'col-md-6',
          'valor' => $orden['falla_reportada'] ?? $precarga['falla_reportada'] ?? '',
          'atributos' => ['rows' => 2, 'placeholder' => 'Lo que cuenta el cliente. Ej: no enciende, se apaga sola, pantalla azul'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'diagnostico', 'etiqueta' => 'Diagnóstico', 'tipo' => 'textarea', 'columna' => 'col-md-6',
          'valor' => $orden['diagnostico'] ?? $precarga['diagnostico'] ?? '',
          'atributos' => ['rows' => 2, 'placeholder' => 'Lo que se detectó al revisarlo'],
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'accesorios', 'etiqueta' => 'Accesorios recibidos', 'columna' => 'col-md-6',
          'valor' => $orden['accesorios'] ?? $precarga['accesorios'] ?? '',
          'atributos' => ['maxlength' => 255, 'placeholder' => 'Ej: cargador original, funda, mouse'],
          'ayuda' => 'Se imprime en el comprobante, para devolver lo mismo que se recibió.',
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'estado_ingreso', 'etiqueta' => 'Estado físico al ingresar', 'tipo' => 'textarea', 'columna' => 'col-md-6',
          'valor' => $orden['estado_ingreso'] ?? $precarga['estado_ingreso'] ?? '',
          'atributos' => ['rows' => 2, 'placeholder' => 'Ej: rayones en la tapa, falta una tecla, bisagra floja'],
        ]) ?>
      </div>

      <?php if ($combos !== []): ?>
        <div class="mb-3 ancho-max-420">
          <label for="agregar_combo" class="form-label">Agregar combo</label>
          <select class="form-select" id="agregar_combo">
            <option value="">Elegí un combo para sumar sus ítems…</option>
            <?php foreach ($combos as $c): ?>
              <option value="<?= (int) $c['id'] ?>" data-items="<?= e(json_encode(array_map(fn($i) => [
                'tipo' => $i['repuesto_id'] !== null ? 'repuesto' : 'servicio',
                'id' => (int) ($i['repuesto_id'] ?? $i['servicio_id']),
                'cantidad' => (float) $i['cantidad'],
              ], $c['items']))) ?>"><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle" id="detalle_orden">
          <thead>
            <tr>
              <th>Ítem</th>
              <th class="ancho-120">Cantidad</th>
              <th class="ancho-170">Precio unitario</th>
              <th class="text-end ancho-150">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr class="js-sin-items"><td colspan="4" class="text-muted">Seleccioná servicios y repuestos para ver el detalle.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="mb-4">
        <label for="total" class="form-label">Total estimado</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="text" class="form-control" id="total" readonly value="<?= $orden ? money($orden['total']) : '0,00' ?>">
        </div>
        <small class="form-text text-muted">
          El precio sugerido es el del catálogo; los ítems que ya estaban en la orden conservan el precio con que se cargaron.
        </small>
      </div>

      <?= $view->partial('componentes/campo', [
        'nombre' => 'trabajo_realizado', 'etiqueta' => 'Trabajo realizado', 'tipo' => 'textarea', 'columna' => 'mb-3',
        'valor' => $orden['trabajo_realizado'] ?? $precarga['trabajo_realizado'] ?? '',
        'atributos' => ['rows' => 2, 'placeholder' => 'Se imprime en el comprobante de entrega'],
      ]) ?>

      <div class="row mb-3 align-items-end">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'proximo_mantenimiento_fecha', 'etiqueta' => 'Próximo mantenimiento', 'tipo' => 'date', 'columna' => 'col-md-4',
          'valor' => $orden['proximo_mantenimiento_fecha'] ?? $precarga['proximo_mantenimiento_fecha'] ?? '',
          'atributos' => ['min' => date('Y-m-d', strtotime('+1 day'))],
        ]) ?>
        <div class="col-md-8">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="sugerir_mantenimiento"
            data-meses="<?= (int) $mantenimiento['intervalo_meses'] ?>">
            Sugerir (dentro de <?= (int) $mantenimiento['intervalo_meses'] ?> meses)
          </button>
          <small class="form-text text-muted d-block">Se le avisa al cliente cuando se acerca la fecha.</small>
        </div>
      </div>

      <?= $view->partial('componentes/campo', [
        'nombre' => 'notas_internas', 'etiqueta' => 'Notas internas', 'tipo' => 'textarea', 'columna' => 'mb-3',
        'etiquetaHtml' => 'Notas internas <small class="text-muted">(no se imprimen ni las ve el cliente)</small>',
        'valor' => $orden['notas_internas'] ?? $precarga['notas_internas'] ?? '', 'atributos' => ['rows' => 2],
      ]) ?>

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $orden ? 'Actualizar orden' : 'Crear orden' ?></button>
      <a href="<?= url('ordenes') ?>" class="btn btn-outline-secondary">Volver al listado</a>
    </form>
  </div>
</div>
