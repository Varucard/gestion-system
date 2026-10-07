<?php
/**
 * @var array<string, mixed>|null $usuario
 * @var list<\App\Enums\Rol> $roles
 */
$activo = $usuario === null || (session()->hasOldInput() ? old('activo') : $usuario['activo']);
?>
<div class="card mt-3">
  <div class="card-header">
    <h4 class="mb-0"><?= $usuario ? 'Editar usuario' : 'Nuevo usuario' ?></h4>
  </div>
  <div class="card-body">
    <form action="<?= url($usuario ? "usuarios/{$usuario['id']}" : 'usuarios') ?>" method="POST">
      <?= csrf_field() ?>
      <div class="row">
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre y apellido *', 'valor' => $usuario['nombre'] ?? '', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['required' => true],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'usuario', 'etiqueta' => 'Usuario *', 'valor' => $usuario['usuario'] ?? '', 'columna' => 'col-md-6 mb-3',
          'atributos' => ['required' => true, 'autocomplete' => 'off'],
        ]) ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'rol', 'etiqueta' => 'Rol *', 'tipo' => 'select', 'valor' => $usuario['rol'] ?? 'empleado', 'columna' => 'col-md-6 mb-3',
          'opciones' => array_combine(array_map(fn($r) => $r->value, $roles), array_map(fn($r) => $r->label(), $roles)),
          'ayuda' => 'Los administradores además gestionan usuarios y la configuración del sistema.',
        ]) ?>
        <?php if ($usuario): ?>
          <div class="col-md-6 mb-3 d-flex align-items-center">
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
              <label class="form-check-label" for="activo">Usuario activo (puede ingresar)</label>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?= $view->partial('usuarios/_campos_clave', ['requerida' => $usuario === null]) ?>
      <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> <?= $usuario ? 'Guardar cambios' : 'Crear usuario' ?></button>
      <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">Volver</a>
    </form>
  </div>
</div>
