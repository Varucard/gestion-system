<form action="<?= url('login') ?>" method="POST">
  <?= csrf_field() ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'usuario', 'etiqueta' => 'Usuario', 'columna' => 'mb-3',
    'atributos' => ['autocomplete' => 'username', 'required' => true, 'autofocus' => true],
  ]) ?>
  <div class="mb-3">
    <label for="clave" class="form-label">Contraseña</label>
    <input type="password" class="form-control" id="clave" name="clave" autocomplete="current-password" required>
  </div>
  <button type="submit" class="btn btn-primary w-100">Ingresar</button>
</form>
<?php if (!empty($mostrador)): ?>
  <p class="small text-center mt-3 mb-0"><a href="<?= url('login') ?>"><?= icono('grid-3x3-gap') ?> Entrar con PIN</a></p>
<?php endif; ?>
