<?php
/**
 * @var array<string, mixed>|null $orden
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $servicios
 * @var list<array<string, mixed>> $repuestos
 * @var array{servicio: array<int, array<string, mixed>>, repuesto: array<int, array<string, mixed>>, pieza: list<array<string, mixed>>} $detalle
 * @var array<string, mixed> $precarga  datos sugeridos al crear (p. ej. desde un turno)
 * @var array<string, mixed> $mantenimiento  configuración del intervalo sugerido
 * @var list<array<string, mixed>> $combos
 */
// Al editar, el técnico asignado debe figurar aunque hoy esté inactivo.
if ($orden && $orden['tecnico_id'] && !in_array((int) $orden['tecnico_id'], array_map('intval', array_column($tecnicos, 'id')), true)) {
  $tecnicos[] = ['id' => $orden['tecnico_id'], 'apellido' => $orden['tecnico'], 'nombre' => null, 'puesto' => 'inactivo'];
}
$view->script('ordenes.js');
// "Al terminar el trabajo" se abre solo si ya tiene algo cargado.
$cierre = ['trabajo_realizado', 'proximo_mantenimiento_fecha', 'notas_internas'];
$abrirCierre = array_filter($cierre, fn($campo) => (string) old($campo, $orden[$campo] ?? $precarga[$campo] ?? '') !== '') !== [];
?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $orden ? "Editar orden #{$orden['id']}" : 'Nueva orden' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($orden ? "ordenes/{$orden['id']}" : 'ordenes') ?>" method="POST" id="form_orden" data-borrador
      data-detalle="<?= e(json_encode($detalle, JSON_FORCE_OBJECT)) ?>"
      data-modos="<?= e(json_encode(\App\Services\OrdenService::MODOS_REPUESTO)) ?>">
      <?= csrf_field() ?>

      <div class="row mb-3">
        <?php if ($orden): ?>
          <?php // Al editar, el equipo no se cambia: la orden, sus pagos y su historial son de ese equipo. ?>
          <div class="col-md-8">
            <span class="form-label d-block">Equipo</span>
            <p class="form-control-plaintext fw-semibold mb-0"><?= e(equipo_texto($orden)) ?></p>
            <input type="hidden" name="equipo_id" value="<?= (int) $orden['equipo_id'] ?>">
          </div>
        <?php else: ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'equipo_id', 'etiqueta' => 'Equipo *', 'tipo' => 'select', 'buscable' => true, 'columna' => 'col-md-8',
          'placeholder' => 'Escribí el apellido, la marca o el n° de serie', 'valor' => $orden['equipo_id'] ?? $equipoSugerido, 'atributos' => ['required' => true],
          'opciones' => array_column(array_map(fn($eq) => [(int) $eq['id'], "{$eq['cliente']} - " . equipo_texto($eq)], $equipos), 1, 0),
          'ayuda' => '¿El equipo no está en la lista? Usá «Llegó un equipo» y lo cargás ahí mismo.',
        ]) ?>
        <?php endif; ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'tecnico_id', 'etiqueta' => 'Técnico asignado', 'tipo' => 'select', 'buscable' => true, 'columna' => 'col-md-4',
          'placeholder' => 'Sin asignar', 'valor' => $orden['tecnico_id'] ?? '',
          'opciones' => array_column(array_map(fn($m) => [
            (int) $m['id'], trim("{$m['apellido']}" . ($m['nombre'] ? ", {$m['nombre']}" : '') . " ({$m['puesto']})"),
          ], $tecnicos), 1, 0),
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

      <?php
      // Plegado (se carga en "Llegó un equipo"): se abre solo si volvió con un error ahí. El resumen
      // muestra lo cargado sin abrirlo, y los trabajos quedan más arriba en la pantalla.
      $errorLlegada = error_campo('accesorios') !== null || error_campo('estado_ingreso') !== null;
      $accesorios = $orden['accesorios'] ?? $precarga['accesorios'] ?? '';
      $estadoIngreso = $orden['estado_ingreso'] ?? $precarga['estado_ingreso'] ?? '';
      $resumenLlegada = implode(' · ', array_filter([
        $accesorios !== '' ? 'con accesorios anotados' : null,
        $estadoIngreso !== '' ? 'con golpes o faltantes anotados' : null,
      ]));
      ?>
      <details class="mas-datos mb-3" <?= $errorLlegada ? 'open' : '' ?>>
        <summary>Cómo llegó <span class="small text-muted">(<?= e($resumenLlegada !== '' ? $resumenLlegada : 'accesorios, golpes, rayones o faltantes') ?>)</span></summary>
        <div class="pt-3 row">
          <?= $view->partial('componentes/campo', [
            'nombre' => 'accesorios', 'etiqueta' => 'Accesorios recibidos', 'columna' => 'col-md-6',
            'valor' => $accesorios,
            'atributos' => ['maxlength' => 255, 'placeholder' => 'Ej: cargador original, funda, mouse'],
            'ayuda' => 'Se imprime en el comprobante, para devolver lo mismo que se recibió.',
          ]) ?>
          <?= $view->partial('componentes/campo', [
            'nombre' => 'estado_ingreso', 'etiqueta' => 'Estado físico al ingresar', 'tipo' => 'textarea', 'columna' => 'col-md-6',
            'valor' => $estadoIngreso,
            'atributos' => ['rows' => 2, 'maxlength' => 1000, 'placeholder' => 'Ej: rayones en la tapa, falta una tecla, bisagra floja'],
          ]) ?>
        </div>
      </details>

      <h5 class="mt-4 mb-1" id="trabajos">Trabajos y repuestos</h5>
      <p class="small text-muted mb-3">Si todavía no sabés qué hay que hacer, podés guardar la orden sin cargarlos y completarlos después.</p>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'servicio_id[]', 'id' => 'servicio_id', 'etiqueta' => 'Servicios', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6', 'clase' => 'js-item-precio', 'placeholder' => 'Escribí para buscar (ej: limpieza)',
          'atributos' => ['multiple' => true, 'data-tipo' => 'servicio', 'data-chip-corto' => true],
          'valor' => array_keys($detalle['servicio']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($s) => [(int) $s['id'], [
            'texto' => "{$s['nombre']} ($ " . money($s['precio_base']) . ')', 'atributos' => ['data-precio' => (float) $s['precio_base']],
          ]], $servicios), 1, 0),
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'repuesto_id[]', 'id' => 'repuesto_id', 'etiqueta' => 'Repuestos', 'tipo' => 'select', 'buscable' => true,
          'columna' => 'col-md-6', 'clase' => 'js-item-precio', 'placeholder' => 'Escribí para buscar (ej: memoria)',
          'atributos' => ['multiple' => true, 'data-tipo' => 'repuesto', 'data-chip-corto' => true],
          'valor' => array_keys($detalle['repuesto']), 'usarAnterior' => false,
          'opciones' => array_column(array_map(fn($r) => [(int) $r['id'], [
            'texto' => "{$r['nombre']} ($ " . money($r['precio']) . ') · stock ' . qty($r['stock_actual']),
            'atributos' => ['data-precio' => (float) $r['precio'], 'data-costo' => $r['precio_costo'] !== null ? (float) $r['precio_costo'] : null],
          ]], $repuestos), 1, 0),
        ]) ?>
      </div>

      <?php if ($combos !== []): ?>
        <div class="mb-3 ancho-max-420">
          <label for="agregar_combo" class="form-label">O sumá un combo armado</label>
          <select class="form-select" id="agregar_combo">
            <option value="">Elegí un combo (ej: limpieza completa)…</option>
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
              <th><span class="visually-hidden">Quitar</span></th>
            </tr>
          </thead>
          <tbody>
            <tr class="js-sin-items"><td colspan="5" class="text-muted">Todavía no hay trabajos ni repuestos. Elegilos arriba y acá aparecen con su precio.</td></tr>
          </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="agregar_pieza">
          <?= icono('plus-lg') ?> Repuesto que trae el cliente y no está en la lista
        </button>
        <small class="form-text text-muted d-block">
          En cada repuesto podés elegir si es <strong>del negocio</strong>, <strong>a costo</strong> (se cobra lo que te salió, sin ganancia)
          o si <strong>lo trae el cliente</strong> (figura sin precio y no se descuenta del stock).
        </small>
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

      <details class="mas-datos mb-4" <?= $abrirCierre ? 'open' : '' ?>>
        <summary>Al terminar el trabajo <span class="small text-muted">(trabajo realizado, próximo mantenimiento, notas internas)</span></summary>
        <div class="pt-3">
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
        </div>
      </details>

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $orden ? 'Guardar cambios' : 'Guardar orden' ?></button>
      <a href="<?= url($orden ? "ordenes/{$orden['id']}" : 'ordenes') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </form>
  </div>
</div>
