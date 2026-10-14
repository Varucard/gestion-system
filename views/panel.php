<?php
/**
 * Inicio: arriba lo que se hace todo el día; abajo, los números del negocio en recuadros
 * grandes que llevan a su listado (no listas: el detalle está en cada pantalla).
 *
 * @var int $turnosHoy
 * @var array{pendiente: int, en_proceso: int, listo: int} $abiertas
 * @var float $cobradoMes
 * @var int $pagosMes  cuántos cobros suman lo cobrado en el mes
 * @var int $deudores
 * @var float $totalAdeudado
 * @var int $stockBajo
 * @var list<array<string, mixed>> $mantenimientos  mantenimientos a vencer en 30 días
 * @var list<array<string, mixed>> $sinRetirar  equipos listos sin retirar hace días
 * @var list<array<string, mixed>> $sinRespuesta  presupuestos enviados sin respuesta
 * @var bool $whatsapp  mostrar los botones de WhatsApp
 */
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mes = $meses[(int) date('n')];
$enLocal = $abiertas['pendiente'] + $abiertas['en_proceso'] + $abiertas['listo'];
$plural = fn(int $n, string $uno, string $varios) => $n . ' ' . ($n === 1 ? $uno : $varios);
// En el inicio los importes van sin centavos: es un resumen, el detalle está en cada listado.
$redondo = fn(float $monto) => '<span class="importe numero-importe">$ ' . number_format(round($monto), 0, ',', '.') . '</span>';

// Lo que se hace todo el día, a un toque: [ruta, ícono, texto, clase, columna]. Buscar no va:
// el buscador ya está arriba de todo. En el celular "Llegó un equipo" tampoco: es el botón del
// medio de la barra de abajo.
$acciones = [
  ['recepcion', 'laptop-fill', 'Llegó un equipo', 'btn-primary', 'd-none d-md-block col-md-4'],
  ['turnos/crear', 'calendar-plus', 'Nuevo turno', 'btn-outline-secondary', 'col-6 col-md-4'],
  ['deudores', 'cash-coin', 'Cobrar', 'btn-outline-secondary', 'col-6 col-md-4'],
];

// Los números del negocio: [ruta, ícono, título, valor (HTML seguro), detalle, color].
$numeros = [
  ['turnos/semana', 'calendar3', 'Turnos de hoy', (string) $turnosHoy, 'Ver la agenda', 'turnos'],
  ['ordenes/tablero', 'laptop', 'Equipos en el local', (string) $enLocal,
    $plural($abiertas['pendiente'], 'recibido', 'recibidos') . ' · ' . $abiertas['en_proceso'] . ' en reparación · '
    . $plural($abiertas['listo'], 'listo', 'listos'), 'ordenes'],
  [auth()->esAdministrador() ? 'reportes' : 'ordenes', 'graph-up-arrow', "Cobrado en {$mes}", $redondo($cobradoMes),
    $pagosMes === 0 ? 'Todavía no se cobró nada' : 'En ' . $plural($pagosMes, 'cobro', 'cobros') . ' desde el 1°', 'clientes'],
  ['deudores', 'exclamation-circle', 'Saldo adeudado', $redondo($totalAdeudado), $plural($deudores, 'cliente debe', 'clientes deben'), 'peligro'],
  ['repuestos', 'box-seam', 'Stock bajo mínimo', (string) $stockBajo, $stockBajo === 0 ? 'Todo en orden' : 'Hay que reponer', 'stock'],
];
?>
<?php if (!empty($primerosPasos)): ?>
  <?php // Se cuentan los necesarios (el aviso se va cuando están hechos); los opcionales siguen en la lista. ?>
  <?php $pendientes = array_values(array_filter($primerosPasos, fn($p) => !$p['hecho'] && !$p['opcional'])); ?>
  <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center primeros-pasos-aviso">
    <span class="me-auto">
      <?= icono('signpost-split') ?> <strong>Primeros pasos:</strong> <?= count($pendientes) === 1 ? 'te falta 1 cosa' : 'te faltan ' . count($pendientes) . ' cosas' ?> para dejar el sistema listo.
      Lo siguiente: <a href="<?= url($pendientes[0]['ruta']) ?>"><?= e(mb_strtolower($pendientes[0]['titulo'])) ?></a>.
    </span>
    <a href="<?= url('primeros-pasos') ?>" class="btn btn-sm btn-primary">Ver los pasos</a>
    <form action="<?= url('primeros-pasos/ocultar') ?>" method="POST" class="d-inline">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-link">No mostrar más</button>
    </form>
  </div>
<?php endif; ?>

<h2 class="visually-hidden">¿Qué querés hacer?</h2>
<div class="row g-2 acciones-rapidas">
  <?php foreach ($acciones as [$ruta, $icono, $texto, $clase, $columna]): ?>
    <div class="<?= e($columna) ?>">
      <a href="<?= url($ruta) ?>" class="btn <?= e($clase) ?> w-100 accion-rapida">
        <?= icono($icono) ?><span><?= e($texto) ?></span>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<h2 class="visually-hidden">El negocio hoy</h2>
<div class="row row-cols-2 row-cols-lg-5 g-3 mt-1">
  <?php foreach ($numeros as $i => [$ruta, $icono, $titulo, $valor, $detalle, $color]): ?>
    <?php // En el celular van de a dos: el último (impar) ocupa todo el ancho para no dejar un hueco. ?>
    <div class="col <?= $i === count($numeros) - 1 && count($numeros) % 2 === 1 ? 'col-12' : '' ?>">
      <a href="<?= url($ruta) ?>" class="card numero-panel numero-<?= e($color) ?> text-decoration-none h-100 mb-0">
        <div class="card-body">
          <div class="numero-titulo"><?= icono($icono) ?> <?= e($titulo) ?></div>
          <div class="numero-valor"><?= $valor ?></div>
          <div class="numero-detalle"><?= e($detalle) ?> <?= icono('chevron-right') ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<?php
// Para no olvidarse: equipos listos que no se retiraron, presupuestos sin respuesta y mantenimientos por
// vencer. Cada uno con su WhatsApp ya escrito (con el link) para avisarle al cliente.
$alertas = count($sinRetirar) + count($sinRespuesta) + count($mantenimientos);
$conTelefono = fn(array $fila) => $whatsapp && strlen(preg_replace('/\D/', '', (string) ($fila['telefono'] ?? ''))) >= 10;
$hace = fn(?string $fecha) => ($n = (int) (new DateTimeImmutable(substr((string) $fecha, 0, 10)))->diff(new DateTimeImmutable('today'))->days) === 1 ? 'hace 1 día' : "hace {$n} días";
?>
<?php if ($alertas > 0): ?>
  <div class="card mt-3 alertas-negocio">
    <div class="card-header"><strong><?= icono('bell') ?> Para no olvidarse</strong> <span class="badge rounded-pill text-bg-warning"><?= $alertas ?></span></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($sinRetirar as $a): ?>
        <li class="list-group-item d-flex flex-wrap gap-2 align-items-center">
          <span class="me-auto">
            <?= icono('laptop') ?> <strong>Listo sin retirar</strong> · <a href="<?= url("ordenes/{$a['id']}") ?>">#<?= (int) $a['id'] ?> · <?= e(equipo_texto($a, false)) ?></a>
            · <?= e($a['cliente']) ?> <span class="text-muted small">(terminado <?= e($hace($a['fecha_realizado'])) ?><?= (float) $a['saldo'] > 0 ? ', debe ' . importe($a['saldo']) : '' ?><?= $a['avisado_en'] ? ' · avisado el ' . e(format_date($a['avisado_en'], 'd/m')) : '' ?>)</span>
          </span>
          <?php if ($whatsapp): ?>
            <?= $view->partial('partials/boton_whatsapp', ['orden' => (int) $a['id'], 'tipo' => 'listo', 'texto' => 'Recordarle', 'clase' => 'btn btn-sm btn-outline-success']) ?>
          <?php endif; ?>
          <a href="<?= url("ordenes/{$a['id']}/entregar") ?>" class="btn btn-sm btn-outline-secondary"><?= icono('key') ?> Ya lo retiró</a>
        </li>
      <?php endforeach; ?>
      <?php foreach ($sinRespuesta as $a): ?>
        <li class="list-group-item d-flex flex-wrap gap-2 align-items-center">
          <span class="me-auto">
            <?= icono('file-text') ?> <strong>Presupuesto sin respuesta</strong> · <a href="<?= url("ordenes/{$a['id']}") ?>">#<?= (int) $a['id'] ?> · <?= e(equipo_texto($a, false)) ?></a>
            · <?= e($a['cliente']) ?> <span class="text-muted small">(enviado <?= e($hace($a['presupuesto_enviado'])) ?>, <?= importe($a['total']) ?>)</span>
          </span>
          <?php if ($whatsapp): ?>
            <?= $view->partial('partials/boton_whatsapp', ['orden' => (int) $a['id'], 'tipo' => 'presupuesto_recordatorio', 'texto' => 'Preguntarle', 'clase' => 'btn btn-sm btn-outline-success']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php foreach ($mantenimientos as $sv): ?>
        <li class="list-group-item d-flex flex-wrap gap-2 align-items-center">
          <span class="me-auto">
            <?= icono('calendar-event') ?> <strong>Mantenimiento el <?= format_date($sv['proximo_mantenimiento_fecha']) ?></strong> ·
            <a href="<?= url("equipos/{$sv['equipo_id']}") ?>"><?= e(equipo_texto($sv)) ?></a> · <?= e($sv['cliente']) ?>
            <span class="small <?= $sv['proximo_mantenimiento_avisado'] ? 'text-success' : 'text-muted' ?>">
              (<?= $sv['proximo_mantenimiento_avisado'] ? 'avisado el ' . format_date($sv['proximo_mantenimiento_avisado']) : 'sin avisar' ?>)
            </span>
          </span>
          <?php if ($whatsapp && !$sv['proximo_mantenimiento_avisado']): ?>
            <?= $view->partial('partials/boton_whatsapp', ['orden' => (int) $sv['id'], 'tipo' => 'mantenimiento', 'texto' => 'Avisarle', 'clase' => 'btn btn-sm btn-outline-success']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
