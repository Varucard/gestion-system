<?php /** @var array<string, mixed>|null $empleado */ ?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $empleado ? 'Editar empleado' : 'Nuevo empleado' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($empleado ? "empleados/{$empleado['id']}" : 'empleados') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'valor' => $empleado['nombre'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['maxlength' => 50, 'required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'apellido', 'etiqueta' => 'Apellido *', 'valor' => $empleado['apellido'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['maxlength' => 50, 'required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'dni', 'etiqueta' => 'DNI *', 'valor' => $empleado['dni'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['inputmode' => 'numeric', 'pattern' => '[0-9]{6,8}', 'maxlength' => 8, 'required' => true, 'readonly' => $empleado !== null],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'puesto', 'etiqueta' => 'Puesto *', 'valor' => $empleado['puesto'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['maxlength' => 50, 'required' => true, 'list' => 'puestos'],
        ]) ?>
        <datalist id="puestos">
          <option value="Técnico"><option value="Ayudante"><option value="Electricista"><option value="Administrativo">
        </datalist>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'tel', 'valor' => $empleado['telefono'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['inputmode' => 'numeric', 'maxlength' => 10],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'fecha_ingreso', 'etiqueta' => 'Fecha de ingreso', 'tipo' => 'date', 'valor' => $empleado['fecha_ingreso'] ?? '', 'columna' => 'col-md-4 mb-3',
          'atributos' => ['max' => date('Y-m-d')],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'email', 'etiqueta' => 'Email', 'tipo' => 'email', 'valor' => $empleado['email'] ?? '', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['maxlength' => 255],
        ]) ?>
      </div>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $empleado ? 'Guardar cambios' : 'Registrar empleado' ?></button>
      <a href="<?= url('empleados') ?>" class="btn btn-outline-secondary">Volver</a>
    </form>
  </div>
</div>
