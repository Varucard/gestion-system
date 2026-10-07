<?php
/** @var array<string, mixed>|null $proveedor */
$activo = $proveedor === null || (session()->hasOldInput() ? old('activo') : $proveedor['activo']);
?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $proveedor ? 'Editar proveedor' : 'Nuevo proveedor' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($proveedor ? "proveedores/{$proveedor['id']}" : 'proveedores') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre / razón social *', 'valor' => $proveedor['nombre'] ?? '', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['maxlength' => 100, 'required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'cuit', 'etiqueta' => 'CUIT', 'valor' => $proveedor['cuit'] ?? '', 'columna' => 'col-md-3 mb-3',
          'atributos' => ['maxlength' => 13, 'placeholder' => '30-12345678-9'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'contacto', 'etiqueta' => 'Persona de contacto', 'valor' => $proveedor['contacto'] ?? '', 'columna' => 'col-md-3 mb-3',
          'atributos' => ['maxlength' => 100],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'tel', 'valor' => $proveedor['telefono'] ?? '', 'columna' => 'col-md-3 mb-3',
          'atributos' => ['maxlength' => 30],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'email', 'etiqueta' => 'Email', 'tipo' => 'email', 'valor' => $proveedor['email'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['maxlength' => 255],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'direccion', 'etiqueta' => 'Dirección', 'valor' => $proveedor['direccion'] ?? '', 'columna' => 'col-md-5 mb-3',
          'atributos' => ['maxlength' => 200],
        ]) ?>
      </div>
      <?= $view->partial('componentes/campo', [
        'nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'valor' => $proveedor['observaciones'] ?? '', 'columna' => 'mb-3',
        'atributos' => ['rows' => 2],
      ]) ?>
      <?php if ($proveedor): ?>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
          <label class="form-check-label" for="activo">Proveedor activo</label>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $proveedor ? 'Guardar cambios' : 'Registrar proveedor' ?></button>
      <a href="<?= url('proveedores') ?>" class="btn btn-outline-secondary">Volver</a>
    </form>
  </div>
</div>
