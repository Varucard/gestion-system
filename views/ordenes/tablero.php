<?php
/**
 * Tablero del local: una columna por etapa (Recibido · En reparación · Listo) con los equipos
 * que están en el local. Las tarjetas se arrastran de una columna a otra (tablero.js); cada
 * una tiene además el menú "Mover a…", que funciona con teclado y sin arrastrar.
 *
 * @var array<string, list<array<string, mixed>>> $columnas  estado => órdenes
 */
use App\Enums\EstadoOrden;

$view->script('vendor/sortable.min.js');
$view->script('tablero.js');

$ayudas = [
  EstadoOrden::Pendiente->value => 'Equipos recibidos que todavía no se empezaron.',
  EstadoOrden::EnProceso->value => 'Lo que se está arreglando.',
  EstadoOrden::Finalizado->value => 'Terminados, esperando que los retiren.',
];
// "Mover a…": las tres etapas. Terminar y volver a abrir se confirman (como en la ficha); a
// "Listo" no se ofrece si la orden no tiene trabajos cargados (tablero.css).
$etapas = [EstadoOrden::Pendiente, EstadoOrden::EnProceso, EstadoOrden::Finalizado];
$confirmar = fn(string $pregunta, string $verbo) => 'data-confirm="' . e($pregunta) . '" data-confirm-aceptar="' . e($verbo) . '"';
$confirmaciones = [EstadoOrden::Finalizado->value => $confirmar(
  '¿Terminar el trabajo? Se descuentan del stock los repuestos usados y, si está activado, se le avisa al cliente que está listo.',
  'Sí, terminar el trabajo',
)];
// Desde "Listo" (la página se recarga al entrar o salir de esa columna, así que el estado es el de ahora).
$reabrir = $confirmar('¿Volver a abrir el trabajo? Los repuestos vuelven al stock hasta que se termine de nuevo.', 'Sí, volver a abrir el trabajo');
// Días desde que la orden se recibió (o, en "Listo", desde que se terminó).
$dias = function (array $o): string {
  $desde = new DateTimeImmutable(substr((string) ($o['fecha_realizado'] ?? $o['created_at']), 0, 10));
  $n = (int) $desde->diff(new DateTimeImmutable('today'))->days;

  $que = $o['fecha_realizado'] ? 'terminado' : 'recibido';

  return $que . ' ' . match (true) {
    $n === 0 => 'hoy',
    $n === 1 => 'ayer',
    default => "hace {$n} días",
  };
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 mb-2">
  <p class="text-muted mb-0 small">Arrastrá cada equipo a la columna que corresponde, o usá «Mover a…» en la tarjeta.</p>
  <div class="d-flex gap-2">
    <a href="<?= url('ordenes') ?>" class="btn btn-outline-secondary"><?= icono('list-ul') ?> Ver como listado</a>
    <a href="<?= url('recepcion') ?>" class="btn btn-seccion"><?= icono('laptop-fill') ?> Llegó un equipo</a>
  </div>
</div>

<div class="tablero" data-url-estado="<?= e(url('ordenes/{id}/estado')) ?>">
  <?php foreach ($columnas as $estado => $ordenes): ?>
    <?php $etapa = EstadoOrden::from($estado); ?>
    <section class="tablero-columna estado-<?= e($estado) ?>" aria-labelledby="columna_<?= e($estado) ?>">
      <header class="tablero-encabezado">
        <h2 class="h6 mb-0" id="columna_<?= e($estado) ?>"><?= e($etapa->label()) ?> <span class="badge rounded-pill tablero-cantidad js-cantidad"><?= count($ordenes) ?></span></h2>
        <small class="text-muted"><?= e($ayudas[$estado]) ?></small>
      </header>
      <div class="tablero-lista js-tablero-lista" data-estado="<?= e($estado) ?>" data-etiqueta="<?= e($etapa->label()) ?>">
        <?php foreach ($ordenes as $o): ?>
          <?php
          $id = (int) $o['id'];
          $sinItems = !(int) $o['con_items'];
          ?>
          <article class="tarjeta-orden" data-id="<?= $id ?>" data-estado="<?= e($estado) ?>" <?= $sinItems ? 'data-sin-items="1"' : '' ?>>
            <div class="d-flex justify-content-between gap-2">
              <a href="<?= url("ordenes/{$id}") ?>" class="fw-semibold">#<?= $id ?> · <?= e(\App\Enums\TipoEquipo::etiqueta($o['tipo'])) ?></a>
              <span class="small text-muted text-nowrap"><?= e($dias($o)) ?></span>
            </div>
            <div><?= e("{$o['marca']} {$o['modelo']}") ?><?= $o['numero_serie'] ? ' <span class="small text-muted">· S/N ' . e($o['numero_serie']) . '</span>' : '' ?></div>
            <div class="small text-muted"><?= e($o['cliente']) ?></div>
            <?php if ($o['tecnico']): ?><div class="small"><?= icono('wrench') ?> <?= e($o['tecnico']) ?></div><?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
              <?php if ($sinItems): ?><span class="badge chip-tarjeta">Sin trabajos cargados</span><?php endif; ?>
              <?php if ($o['presupuesto_respuesta'] === 'aceptado'): ?>
                <span class="badge chip-tarjeta chip-ok"><?= icono('check-lg') ?> Presupuesto aceptado</span>
              <?php elseif ($o['presupuesto_respuesta'] === 'rechazado'): ?>
                <span class="badge chip-tarjeta chip-mal"><?= icono('x-lg') ?> Presupuesto rechazado</span>
              <?php elseif ($o['presupuesto_enviado'] && $estado !== EstadoOrden::Finalizado->value): ?>
                <span class="badge chip-tarjeta">Presupuesto sin respuesta</span>
              <?php endif; ?>
              <?php if ($estado === EstadoOrden::Finalizado->value && (float) $o['saldo'] > 0): ?>
                <span class="badge chip-tarjeta chip-mal">Debe <?= importe($o['saldo']) ?></span>
              <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-2">
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                  aria-label="Mover la orden #<?= $id ?> a otra etapa">Mover a…</button>
                <?php // Van las tres etapas: la actual se oculta con CSS según data-estado de la tarjeta, así el menú sigue bien después de arrastrarla. ?>
                <ul class="dropdown-menu">
                  <?php foreach ($etapas as $destino): ?>
                    <li class="mover-a" data-destino="<?= e($destino->value) ?>">
                      <form action="<?= url("ordenes/{$id}/estado") ?>" method="POST" <?= $confirmaciones[$destino->value] ?? ($estado === EstadoOrden::Finalizado->value ? $reabrir : '') ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="estado" value="<?= e($destino->value) ?>">
                        <input type="hidden" name="volver" value="tablero">
                        <button type="submit" class="dropdown-item"><?= e($destino->label()) ?></button>
                      </form>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php if ($estado === EstadoOrden::Finalizado->value): ?>
                <a href="<?= url("ordenes/{$id}/entregar") ?>" class="btn btn-sm btn-success"><?= icono('key') ?> Entregar</a>
              <?php elseif ($sinItems): ?>
                <a href="<?= url("ordenes/{$id}/editar") ?>" class="btn btn-sm btn-outline-primary"><?= icono('list-check') ?> Cargar trabajos</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <p class="tablero-vacia small text-muted mb-0">Sin equipos en esta etapa.</p>
      </div>
    </section>
  <?php endforeach; ?>
</div>
