<?php
/**
 * "Llegó un equipo": DNI → (cliente nuevo: sus datos) → qué equipo trae → falla → cómo viene → orden abierta.
 *
 * El formulario cambia según lo que se sabe del DNI (data-estado del <form>):
 *   sin-buscar  todavía no se buscó: solo se ve el paso 1
 *   conocido    el cliente ya está cargado: se elige uno de sus equipos (o se carga otro)
 *   nuevo       primera vez: se piden los datos del cliente y del equipo
 * Los datos de un cliente o un equipo nuevos van en un <fieldset> que se deshabilita cuando no
 * hacen falta: así no se validan ni se envían.
 *
 * @var array<string, mixed>|null $turno
 * @var string $dni
 * @var array<string, mixed>|null $cliente
 * @var list<array<string, mixed>> $equiposCliente  con 'abiertas' (órdenes que siguen en el local)
 * @var string $equipoElegido  id del equipo elegido ('' = uno nuevo)
 * @var string $falla
 * @var list<\App\Enums\TipoEquipo> $tipos
 * @var list<array<string, mixed>> $marcas
 * @var list<array<string, mixed>> $modelos
 * @var list<array<string, mixed>> $tecnicos
 */
use App\Services\EquipoService;

$view->script('equipos.js');
$view->script('recepcion.js');

$estado = match (true) {
  $cliente !== null => 'conocido',
  $dni !== '' && preg_match('/^\d{6,8}$/', preg_replace('/\D/', '', $dni)) === 1 => 'nuevo',
  default => 'sin-buscar',
};
// Con un cliente conocido se elige uno de sus equipos; si no tiene, o se eligió "otro", se carga uno nuevo.
$ids = array_map(fn($e) => (string) $e['id'], $equiposCliente);
$elegido = in_array($equipoElegido, $ids, true) ? $equipoElegido : ($equiposCliente !== [] && $equipoElegido === '' && !session()->hasOldInput() ? $ids[0] : '');
$equipoNuevo = $estado === 'nuevo' || ($estado === 'conocido' && $elegido === '');
$abierta = null;
foreach ($equiposCliente as $e) {
  if ((string) $e['id'] === $elegido && $e['abiertas'] !== []) {
    $abierta = max(array_map(fn($o) => (int) $o['id'], $e['abiertas']));
  }
}

$marcaId = (int) old('marca_id', 0);
$modeloId = (int) old('modelo_id', 0);
$modeloNuevo = str_starts_with((string) old('modelo_id', ''), EquipoService::MODELO_NUEVO) ? (string) old('modelo_id') : null;
$tipoElegido = (string) old('tipo', '');
?>
<form action="<?= url('recepcion') ?>" method="POST" enctype="multipart/form-data" id="form_recepcion" class="recepcion mt-3" data-borrador data-estado="<?= e($estado) ?>"
  data-cliente-url="<?= e(url('recepcion/cliente')) ?>" data-orden-url="<?= e(url('ordenes/{id}')) ?>" data-modelos-url="<?= e(url('marcas/{id}/modelos')) ?>">
  <?= csrf_field() ?>

  <?php if ($turno): ?>
    <input type="hidden" name="turno_id" value="<?= (int) $turno['id'] ?>">
    <div class="alert alert-info d-flex gap-2 align-items-center">
      <?= icono('calendar-check') ?>
      <span>Viene por el turno del <?= format_date($turno['fecha']) ?> a las <?= e(substr($turno['hora'], 0, 5)) ?>. Al recibirlo, el turno queda como <strong>realizado</strong>.</span>
    </div>
  <?php endif; ?>

  <section class="paso">
    <h2 class="paso-titulo"><span class="paso-numero">1</span> ¿De quién es el equipo?</h2>
    <label for="dni" class="form-label">DNI del cliente</label>
    <div class="input-group input-group-lg ancho-max-420">
      <input type="text" class="form-control<?= error_campo('dni') !== null ? ' is-invalid' : '' ?>" id="dni" name="dni" value="<?= e($dni) ?>" required autocomplete="off"
        inputmode="numeric" maxlength="10" placeholder="Ej: 30111222" <?= $estado === 'sin-buscar' ? 'autofocus' : '' ?>
        pattern="\s*[0-9.\s]{6,10}\s*" data-error="El DNI va solo con números (6 a 8 dígitos).">
      <button type="button" class="btn btn-seccion js-buscar-dni"><?= icono('search') ?> Buscar</button>
    </div>
    <?= $view->partial('componentes/error_campo', ['nombre' => 'dni']) ?>
    <small class="form-text text-muted d-block">Si ya es cliente, lo encontramos con el DNI y vemos sus equipos.</small>

    <div id="resultado_dni" class="mt-3" aria-live="polite">
      <?php if ($cliente): ?>
        <div class="alert alert-success mb-0">
          <?= icono('check-circle-fill') ?> Ya es cliente: <strong><?= e("{$cliente['apellido']}, {$cliente['nombre']}") ?></strong>.
          <a href="<?= url("clientes/{$cliente['id']}") ?>">Ver ficha</a>
          <?= $cliente['estado'] !== 'activo' ? ' (estaba dado de baja: al recibir el equipo se vuelve a activar)' : '' ?>
        </div>
      <?php elseif ($estado === 'nuevo'): ?>
        <div class="alert alert-info mb-0"><?= icono('info-circle') ?> Cliente nuevo: completá sus datos y los del equipo.</div>
      <?php endif; ?>
    </div>

    <fieldset id="cliente_nuevo" class="row mt-3" <?= $estado === 'nuevo' ? '' : 'disabled hidden' ?>>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'columna' => 'col-md-6 mb-3', 'atributos' => ['maxlength' => 50, 'required' => true, 'autocomplete' => 'off'],
      ]) ?>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'apellido', 'etiqueta' => 'Apellido *', 'columna' => 'col-md-6 mb-3', 'atributos' => ['maxlength' => 50, 'required' => true, 'autocomplete' => 'off'],
      ]) ?>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'telefono', 'etiqueta' => 'Teléfono *', 'tipo' => 'tel', 'columna' => 'col-md-6 mb-3',
        'atributos' => ['inputmode' => 'tel', 'maxlength' => 20, 'placeholder' => 'Ej: 11 2345-6789', 'required' => true, 'data-telefono' => true],
        'ayuda' => 'Con el código de área. Puede ir con 0, con 15, con espacios o guiones.',
      ]) ?>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'email', 'etiqueta' => 'Email (si tiene)', 'tipo' => 'email', 'columna' => 'col-md-6 mb-3',
        'atributos' => ['maxlength' => 255, 'placeholder' => 'ejemplo@correo.com'],
        'ayuda' => 'Sirve para mandarle el presupuesto y avisarle cuando está listo.',
      ]) ?>
    </fieldset>
  </section>

  <div class="js-tras-buscar" <?= $estado === 'sin-buscar' ? 'hidden' : '' ?>>
    <section class="paso">
      <h2 class="paso-titulo"><span class="paso-numero">2</span> ¿Qué equipo trae?</h2>

      <?php // Equipos del cliente: los arma recepcion.js al buscar el DNI (o acá, si ya se sabía). ?>
      <div id="equipos_cliente" class="equipos-cliente mb-3" role="radiogroup" aria-label="Equipos del cliente" <?= $estado === 'conocido' ? '' : 'hidden' ?>>
        <?php foreach ($equiposCliente as $e): ?>
          <?php $abiertaEquipo = $e['abiertas'] === [] ? null : max(array_map(fn($o) => (int) $o['id'], $e['abiertas'])); ?>
          <label class="equipo-opcion">
            <input type="radio" class="form-check-input" name="equipo_id" value="<?= (int) $e['id'] ?>" <?= $elegido === (string) $e['id'] ? 'checked' : '' ?>
              <?= $abiertaEquipo ? 'data-abierta="' . $abiertaEquipo . '"' : '' ?>>
            <span><?= e(equipo_texto($e)) ?>
              <?php if ($abiertaEquipo): ?><span class="badge text-bg-warning ms-1">En el local · orden #<?= $abiertaEquipo ?></span><?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
        <label class="equipo-opcion">
          <input type="radio" class="form-check-input" name="equipo_id" value="" <?= $elegido === '' && $estado === 'conocido' ? 'checked' : '' ?>>
          <span><?= icono('plus-lg') ?> Otro equipo (lo cargo ahora)</span>
        </label>
      </div>
      <?= $view->partial('componentes/error_campo', ['nombre' => 'equipo_id']) ?>

      <div id="aviso_abierta" aria-live="polite">
        <?php if ($abierta): ?>
          <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
            <span class="me-auto"><?= icono('exclamation-triangle') ?> Este equipo ya está en el local: tiene la orden #<?= $abierta ?> abierta o lista sin retirar.</span>
            <a href="<?= url("ordenes/{$abierta}") ?>" class="btn btn-primary"><?= icono('arrow-right') ?> Seguir con la orden #<?= $abierta ?></a>
          </div>
        <?php endif; ?>
      </div>

      <fieldset id="equipo_nuevo" <?= $equipoNuevo ? '' : 'disabled hidden' ?>>
        <div class="row">
          <?= $view->partial('componentes/campo', [
            'nombre' => 'tipo', 'etiqueta' => 'Tipo de equipo *', 'tipo' => 'select', 'columna' => 'col-md-6 mb-3', 'valor' => $tipoElegido,
            'opciones' => ['' => 'Elegí el tipo'] + array_combine(array_map(fn($t) => $t->value, $tipos), array_map(fn($t) => $t->label(), $tipos)),
            'atributos' => ['required' => true],
          ]) ?>
          <?= $view->partial('componentes/campo', [
            'nombre' => 'numero_serie', 'etiqueta' => 'N° de serie (si se ve)', 'columna' => 'col-md-6 mb-3', 'clase' => 'text-uppercase',
            'atributos' => ['maxlength' => 50, 'placeholder' => 'Ej: PF2ABC12', 'autocomplete' => 'off'],
            'ayuda' => 'Suele estar en una etiqueta debajo del equipo o en la caja.',
          ]) ?>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="marca_id" class="form-label">Marca *</label>
            <select class="form-select js-buscable<?= error_campo('marca_id') !== null ? ' is-invalid' : '' ?>" id="marca_id" name="marca_id" data-crear="<?= e(EquipoService::MODELO_NUEVO) ?>" data-placeholder="Elegí o escribí la marca" required>
              <option value=""></option>
              <?php foreach ($marcas as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= selected($marcaId === (int) $m['id']) ?>><?= e($m['nombre']) ?></option>
              <?php endforeach; ?>
              <?php if (str_starts_with((string) old('marca_id', ''), EquipoService::MODELO_NUEVO)): ?>
                <option value="<?= e((string) old('marca_id')) ?>" selected><?= e(substr((string) old('marca_id'), strlen(EquipoService::MODELO_NUEVO))) ?></option>
              <?php endif; ?>
            </select>
            <?= $view->partial('componentes/error_campo', ['nombre' => 'marca_id']) ?>
          </div>
          <div class="col-md-6 mb-3">
            <label for="modelo_id" class="form-label">Modelo *</label>
            <select class="form-select js-buscable<?= error_campo('modelo_id') !== null ? ' is-invalid' : '' ?>" id="modelo_id" name="modelo_id" data-crear="<?= e(EquipoService::MODELO_NUEVO) ?>"
              <?php $hayMarca = $marcaId > 0 || str_starts_with((string) old('marca_id', ''), EquipoService::MODELO_NUEVO); ?>
              data-placeholder="<?= $hayMarca ? 'Elegí o escribí el modelo' : 'Primero elegí la marca' ?>" required <?= $hayMarca ? '' : 'disabled' ?>>
              <option value=""></option>
              <?php foreach ($modelos as $mo): ?>
                <option value="<?= (int) $mo['id'] ?>" <?= selected($modeloId === (int) $mo['id']) ?>><?= e($mo['nombre']) ?></option>
              <?php endforeach; ?>
              <?php if ($modeloNuevo): ?>
                <option value="<?= e($modeloNuevo) ?>" selected><?= e(substr($modeloNuevo, strlen(EquipoService::MODELO_NUEVO))) ?></option>
              <?php endif; ?>
            </select>
            <?= $view->partial('componentes/error_campo', ['nombre' => 'modelo_id']) ?>
            <small class="form-text text-muted">Si no está, escribilo y elegí «Agregar».</small>
          </div>
        </div>
        <p class="small text-muted">Procesador, memoria y el resto de los datos se completan después en la ficha del equipo, si hacen falta.</p>
      </fieldset>
    </section>

    <section class="paso">
      <h2 class="paso-titulo"><span class="paso-numero">3</span> ¿Qué le pasa?</h2>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'falla_reportada', 'etiqueta' => 'Lo que cuenta el cliente', 'tipo' => 'textarea', 'columna' => 'col-12 mb-3',
          'valor' => $falla,
          'atributos' => ['rows' => 3, 'placeholder' => 'Ej: no enciende, se apaga sola, pantalla azul, limpieza y cambio de pasta térmica…'],
        ]) ?>
        <?php if ($tecnicos !== []): ?>
          <?= $view->partial('componentes/campo', [
            'nombre' => 'tecnico_id', 'etiqueta' => '¿Quién lo va a atender?', 'tipo' => 'select', 'columna' => 'col-md-8 mb-3',
            'opciones' => ['' => 'Todavía no sé'] + array_column(array_map(fn($t) => [
              (int) $t['id'], trim("{$t['apellido']}" . ($t['nombre'] ? ", {$t['nombre']}" : '')),
            ], $tecnicos), 1, 0),
          ]) ?>
        <?php endif; ?>
      </div>
      <p class="small text-muted">Los servicios y repuestos se cargan después, en la orden, cuando ya se sabe qué hay que hacer.</p>
    </section>

    <?php // Para que no haya discusiones al entregarlo: con qué vino, cómo llegó, fotos y la firma del cliente. Todo opcional. ?>
    <section class="paso">
      <h2 class="paso-titulo"><span class="paso-numero">4</span> ¿Cómo viene? <small class="text-muted fw-normal fs-6">(opcional)</small></h2>
      <?php if (session()->hasOldInput()): ?>
        <?php // Los archivos y el dibujo no vuelven con el formulario: se dice, para no darlos por cargados. ?>
        <div class="alert alert-warning py-2"><?= icono('camera') ?> Si habías sacado fotos o el cliente había firmado, hay que volver a hacerlo: no se guardan cuando falta corregir algún dato.</div>
      <?php endif; ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'accesorios', 'etiqueta' => 'Accesorios que deja', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['maxlength' => 255, 'placeholder' => 'Ej: cargador original, funda, mouse'],
          'ayuda' => 'Se imprime en el comprobante, para devolver lo mismo que se recibió.',
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'estado_ingreso', 'etiqueta' => 'Golpes, rayones o faltantes', 'tipo' => 'textarea', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['rows' => 2, 'maxlength' => 1000, 'placeholder' => 'Ej: rayones en la tapa, falta una tecla, bisagra floja…'],
        ]) ?>
      </div>
      <div class="mb-3">
        <?= $view->partial('componentes/fotos') ?>
      </div>
      <details class="mas-datos">
        <summary>Que el cliente firme que el equipo llegó así</summary>
        <div class="mt-3">
          <?= $view->partial('componentes/firma', ['texto' => 'El cliente firma que deja el equipo en el estado descripto (accesorios, golpes y fotos).']) ?>
        </div>
      </details>
    </section>

    <?php // Si el equipo ya tiene una orden abierta, abrir otra es la opción secundaria y se confirma. ?>
    <?php // confirmar_otra va vacío siempre: lo completa recepcion.js con el N° de la orden abierta, recién cuando se confirma. ?>
    <input type="hidden" name="confirmar_otra" id="confirmar_otra" value="">
    <div class="d-flex flex-wrap gap-2">
      <button type="submit" class="btn btn-lg js-recibir <?= $abierta ? 'btn-outline-secondary' : 'btn-seccion' ?>"
        data-texto-normal="Recibir el equipo" data-texto-otra="Abrir otra orden igual" <?= $abierta ? 'data-abierta="' . $abierta . '"' : '' ?>>
        <?= icono('check-lg') ?> <span class="js-recibir-texto"><?= $abierta ? 'Abrir otra orden igual' : 'Recibir el equipo' ?></span>
      </button>
      <a href="<?= url('/') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
  </div>
</form>
