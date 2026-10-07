<?php /** @var array<string, mixed>|null $cliente */ ?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $cliente ? 'Editar cliente' : 'Nuevo cliente' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($cliente ? "clientes/{$cliente['id']}" : 'clientes') ?>" method="POST">
      <?= csrf_field() ?>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre *', 'valor' => $cliente['nombre'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['maxlength' => 50, 'required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'apellido', 'etiqueta' => 'Apellido *', 'valor' => $cliente['apellido'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['maxlength' => 50, 'required' => true],
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'dni', 'etiqueta' => 'DNI *', 'valor' => $cliente['dni'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['inputmode' => 'numeric', 'pattern' => '[0-9]{6,8}', 'maxlength' => 8, 'required' => true, 'readonly' => $cliente !== null],
          'ayuda' => $cliente ? 'El DNI no se puede modificar.' : null,
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'telefono', 'etiqueta' => 'Teléfono *', 'tipo' => 'tel', 'valor' => $cliente['telefono'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['inputmode' => 'numeric', 'pattern' => '[0-9]{10}', 'maxlength' => 10, 'placeholder' => '1123456789', 'required' => true],
          'ayuda' => '10 dígitos: código de área + número, sin 0 ni 15.',
        ]) ?>
      </div>

      <div class="row mb-3">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'email', 'etiqueta' => 'Email (opcional)', 'tipo' => 'email', 'valor' => $cliente['email'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['maxlength' => 255, 'placeholder' => 'ejemplo@correo.com'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'direccion', 'etiqueta' => 'Dirección (opcional)', 'valor' => $cliente['direccion'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['minlength' => 5, 'maxlength' => 200],
        ]) ?>
      </div>

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $cliente ? 'Actualizar cliente' : 'Registrar cliente' ?></button>
      <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary">Volver al listado</a>
    </form>
  </div>
</div>
