<?php
/**
 * @var array<string, mixed>|null $equipo
 * @var list<array<string, mixed>> $clientes
 * @var list<array<string, mixed>> $marcas
 * @var list<array<string, mixed>> $modelos
 */
$clienteId = (int) old('cliente_id', $equipo['cliente_id'] ?? $clienteSugerido);
$marcaId = (int) old('marca_id', $equipo['marca_id'] ?? 0);
$modeloId = (int) old('modelo_id', $equipo['modelo_id'] ?? 0);
// Modelo escrito a mano que volvió con errores: se vuelve a mostrar elegido.
$modeloNuevo = str_starts_with((string) old('modelo_id', ''), \App\Services\EquipoService::MODELO_NUEVO) ? (string) old('modelo_id') : null;
$view->script('equipos.js');
?>
<?php if (!empty($paraTurno)): ?>
  <div class="alert alert-info mt-3 mb-0"><?= icono('calendar-plus') ?> Turno para un cliente nuevo · paso 2 de 3: el equipo. Al guardar, volvés al turno con el cliente ya elegido.</div>
<?php endif; ?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $equipo ? 'Editar equipo' : 'Nuevo equipo' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($equipo ? "equipos/{$equipo['id']}" : 'equipos') ?>" method="POST" id="form_equipo" data-borrador
      data-nuevo="<?= $equipo ? '0' : '1' ?>"
      data-equipos-url="<?= e(url('clientes/{id}/equipos')) ?>"
      data-modelos-url="<?= e(url('marcas/{id}/modelos')) ?>">
      <?= csrf_field() ?>
      <?php if (!empty($paraTurno)): ?><input type="hidden" name="para" value="turno"><?php endif; ?>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="cliente_id" class="form-label">Cliente *</label>
          <select class="form-select js-buscable<?= error_campo('cliente_id') !== null ? ' is-invalid' : '' ?>" id="cliente_id" name="cliente_id" data-placeholder="Escribí el apellido o el DNI" required>
            <option value=""></option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= selected($clienteId === (int) $c['id']) ?>>
                <?= e("{$c['apellido']}, {$c['nombre']} - DNI: {$c['dni']}") ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= $view->partial('componentes/error_campo', ['nombre' => 'cliente_id']) ?>
        </div>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'tipo', 'etiqueta' => 'Tipo de equipo *', 'tipo' => 'select', 'valor' => $equipo['tipo'] ?? '', 'columna' => 'col-md-6',
          'opciones' => ['' => 'Seleccione el tipo'] + array_combine(
            array_map(fn($t) => $t->value, $tipos),
            array_map(fn($t) => $t->label(), $tipos),
          ),
          'atributos' => ['required' => true],
        ]) ?>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="marca_id" class="form-label">Marca *</label>
          <select class="form-select js-buscable<?= error_campo('marca_id') !== null ? ' is-invalid' : '' ?>" id="marca_id" name="marca_id" data-crear="<?= e(\App\Services\EquipoService::MODELO_NUEVO) ?>" data-placeholder="Elegí o escribí la marca" required>
            <option value=""></option>
            <?php foreach ($marcas as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= selected($marcaId === (int) $m['id']) ?>><?= e($m['nombre']) ?></option>
            <?php endforeach; ?>
            <?php if (str_starts_with((string) old('marca_id', ''), \App\Services\EquipoService::MODELO_NUEVO)): ?>
              <option value="<?= e((string) old('marca_id')) ?>" selected><?= e(substr((string) old('marca_id'), strlen(\App\Services\EquipoService::MODELO_NUEVO))) ?></option>
            <?php endif; ?>
          </select>
          <?= $view->partial('componentes/error_campo', ['nombre' => 'marca_id']) ?>
        </div>
        <div class="col-md-4">
          <label for="modelo_id" class="form-label">Modelo *</label>
          <select class="form-select js-buscable<?= error_campo('modelo_id') !== null ? ' is-invalid' : '' ?>" id="modelo_id" name="modelo_id" data-crear="<?= e(\App\Services\EquipoService::MODELO_NUEVO) ?>"
            <?php $hayMarca = $marcaId > 0 || str_starts_with((string) old('marca_id', ''), \App\Services\EquipoService::MODELO_NUEVO); ?>
            data-placeholder="<?= $hayMarca ? 'Elegí o escribí el modelo' : 'Primero elegí la marca' ?>" required <?= $hayMarca ? '' : 'disabled' ?>>
            <option value=""></option>
            <?php foreach ($modelos as $mo): ?>
              <option value="<?= (int) $mo['id'] ?>" <?= selected($modeloId === (int) $mo['id']) ?>><?= e($mo['nombre']) ?></option>
            <?php endforeach; ?>
            <?php if ($modeloNuevo): ?>
              <option value="<?= e($modeloNuevo) ?>" selected><?= e(substr($modeloNuevo, strlen(\App\Services\EquipoService::MODELO_NUEVO))) ?></option>
            <?php endif; ?>
          </select>
          <?= $view->partial('componentes/error_campo', ['nombre' => 'modelo_id']) ?>
          <small class="form-text text-muted">Si no está en la lista, escribilo y elegí «Agregar».</small>
        </div>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'numero_serie', 'etiqueta' => 'N° de serie', 'valor' => $equipo['numero_serie'] ?? '', 'columna' => 'col-md-4', 'clase' => 'text-uppercase',
          'atributos' => ['maxlength' => 50, 'placeholder' => 'Ej: PF2ABC12'],
          'ayuda' => 'Suele estar en una etiqueta debajo del equipo o en la caja.',
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'procesador', 'etiqueta' => 'Procesador', 'valor' => $equipo['procesador'] ?? '', 'columna' => 'col-md-3',
          'atributos' => ['maxlength' => 80, 'placeholder' => 'Ej: Intel Core i5-1135G7'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'memoria', 'etiqueta' => 'Memoria RAM', 'valor' => $equipo['memoria'] ?? '', 'columna' => 'col-md-3',
          'atributos' => ['maxlength' => 30, 'placeholder' => 'Ej: 8 GB DDR4'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'almacenamiento', 'etiqueta' => 'Almacenamiento', 'valor' => $equipo['almacenamiento'] ?? '', 'columna' => 'col-md-3',
          'atributos' => ['maxlength' => 50, 'placeholder' => 'Ej: SSD 256 GB + HDD 1 TB'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'color', 'etiqueta' => 'Color', 'valor' => $equipo['color'] ?? '', 'columna' => 'col-md-3',
          'atributos' => ['maxlength' => 30],
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'detalle', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'valor' => $equipo['detalle'] ?? '', 'columna' => 'col-12',
          'atributos' => ['rows' => 2, 'placeholder' => 'Ej: sistema operativo, bisagra floja, tiene stickers en la tapa'],
        ]) ?>
      </div>

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $equipo ? 'Guardar cambios' : 'Guardar equipo' ?></button>
      <a href="<?= url($equipo ? "equipos/{$equipo['id']}" : 'equipos') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </form>
  </div>
</div>
