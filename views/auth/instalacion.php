<?php if (!$conToken): ?>
  <p class="text-muted">No hay usuarios todavía. Por seguridad, el primer administrador se crea desde la consola del servidor:</p>
  <pre class="bg-body-tertiary p-2 rounded"><code>php bin/usuario.php crear &lt;usuario&gt; "&lt;Nombre y apellido&gt;"</code></pre>
  <p class="text-muted small">Con Docker, adelante va <code>docker compose exec</code> y el nombre del servicio de la aplicación (por ejemplo, <code>public</code>).</p>
  <p class="text-muted small mb-0">O, para crearlo desde acá, definí <code>INSTALACION_TOKEN</code> en el <code>.env</code> y reiniciá el sistema: esta pantalla lo va a pedir.</p>
<?php else: ?>
<p class="text-muted">No hay usuarios todavía. Creá la cuenta del administrador del sistema.</p>
<form action="<?= url('instalacion') ?>" method="POST">
  <?= csrf_field() ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'token_instalacion', 'etiqueta' => 'Código de instalación *', 'columna' => 'mb-3', 'tipo' => 'password', 'usarAnterior' => false,
    'ayuda' => 'El valor de INSTALACION_TOKEN en el .env del servidor.',
    'atributos' => ['autocomplete' => 'off', 'required' => true],
  ]) ?>
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
<?php endif; ?>
