<?php /** @var array<string, mixed>|null $cliente @var bool|null $paraTurno */ ?>
<?php if (!empty($paraTurno)): ?>
  <div class="alert alert-info mt-3 mb-0"><?= icono('calendar-plus') ?> Turno para un cliente nuevo · paso 1 de 3: los datos del cliente. Después cargás su equipo y volvés al turno.</div>
<?php elseif (!$cliente): ?>
  <div class="alert alert-light border mt-3 mb-0 d-flex gap-2 align-items-center">
    <?= icono('lightbulb') ?>
    <span>¿El cliente vino con el equipo? Usá <a href="<?= url('recepcion') ?>">Llegó un equipo</a>: cargás el cliente, el equipo y la orden de una sola vez.</span>
  </div>
<?php endif; ?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $cliente ? 'Editar cliente' : 'Nuevo cliente' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($cliente ? "clientes/{$cliente['id']}" : 'clientes') ?>" method="POST" id="form_cliente" data-borrador>
      <?= csrf_field() ?>
      <?php if (!empty($paraTurno)): ?><input type="hidden" name="para" value="turno"><?php endif; ?>

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
          'atributos' => ['inputmode' => 'numeric', 'pattern' => '[0-9]{6,8}', 'maxlength' => 8, 'required' => true, 'readonly' => $cliente !== null,
            'data-error' => 'El DNI va solo con números, sin puntos (6 a 8 dígitos).'],
          'ayuda' => $cliente ? 'El DNI no se puede modificar.' : null,
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'telefono', 'etiqueta' => 'Teléfono *', 'tipo' => 'tel', 'valor' => $cliente['telefono'] ?? '', 'columna' => 'col-md-6',
          'atributos' => ['inputmode' => 'tel', 'maxlength' => 20, 'placeholder' => 'Ej: 11 2345-6789', 'required' => true, 'data-telefono' => true],
          'ayuda' => 'Con el código de área. Puede ir con 0, con 15, con espacios o guiones.',
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

      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $cliente ? 'Guardar cambios' : 'Guardar cliente' ?></button>
      <a href="<?= url($cliente ? "clientes/{$cliente['id']}" : 'clientes') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </form>
  </div>
</div>
