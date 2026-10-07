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
$view->script('equipos.js');
?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $equipo ? 'Editar equipo' : 'Nuevo equipo' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($equipo ? "equipos/{$equipo['id']}" : 'equipos') ?>" method="POST" id="form_equipo"
      data-nuevo="<?= $equipo ? '0' : '1' ?>"
      data-equipos-url="<?= e(url('clientes/{id}/equipos')) ?>"
      data-modelos-url="<?= e(url('marcas/{id}/modelos')) ?>">
      <?= csrf_field() ?>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="cliente_id" class="form-label">Cliente *</label>
          <select class="form-select js-buscable" id="cliente_id" name="cliente_id" data-placeholder="Seleccione un cliente" required>
            <option value=""></option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= selected($clienteId === (int) $c['id']) ?>>
                <?= e("{$c['apellido']}, {$c['nombre']} - DNI: {$c['dni']}") ?>
              </option>
            <?php endforeach; ?>
          </select>
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
          <select class="form-select js-buscable" id="marca_id" name="marca_id" data-placeholder="Seleccione una marca" required>
            <option value=""></option>
            <?php foreach ($marcas as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= selected($marcaId === (int) $m['id']) ?>><?= e($m['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="modelo_id" class="form-label">Modelo *</label>
          <select class="form-select js-buscable" id="modelo_id" name="modelo_id" data-placeholder="Seleccione primero una marca"
            required <?= $modelos === [] ? 'disabled' : '' ?>>
            <option value=""></option>
            <?php foreach ($modelos as $mo): ?>
              <option value="<?= (int) $mo['id'] ?>" <?= selected($modeloId === (int) $mo['id']) ?>><?= e($mo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'numero_serie', 'etiqueta' => 'N° de serie', 'valor' => $equipo['numero_serie'] ?? '', 'columna' => 'col-md-4', 'clase' => 'text-uppercase',
          'atributos' => ['maxlength' => 50, 'placeholder' => 'Suele estar en la etiqueta de abajo'],
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

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $equipo ? 'Actualizar' : 'Registrar' ?> equipo</button>
      <a href="<?= url('equipos') ?>" class="btn btn-outline-secondary">Ver equipos registrados</a>
    </form>
  </div>
</div>
