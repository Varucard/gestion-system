<p class="text-muted">No hay usuarios todavía. Creá la cuenta del administrador del sistema.</p>
<form action="<?= url('instalacion') ?>" method="POST">
  <?= csrf_field() ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'nombre', 'etiqueta' => 'Nombre y apellido *', 'columna' => 'mb-3',
    'atributos' => ['required' => true],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'usuario', 'etiqueta' => 'Usuario *', 'columna' => 'mb-3',
    'atributos' => ['autocomplete' => 'username', 'required' => true],
  ]) ?>
  <?= $view->partial('usuarios/_campos_clave', ['requerida' => true]) ?>
  <button type="submit" class="btn btn-primary w-100">Crear administrador</button>
</form>
