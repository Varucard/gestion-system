<?php
/**
 * @var list<array<string, mixed>> $mostradores
 * @var array<string, mixed>|null $esteEquipo  el mostrador de este navegador, si lo es
 * @var list<array<string, mixed>> $conPin  usuarios que ya tienen PIN
 */
?>
<p class="mt-3 mb-0"><a href="<?= url('configuracion') ?>"><?= icono('arrow-left') ?> Volver a Configuración</a></p>
<div class="card mt-2">
  <div class="card-body">
    <p>
      En la PC o la tablet que usan varios, cada uno entra <strong>tocando su nombre y con un PIN de 4 números</strong>, sin escribir
      usuario y contraseña. La sesión se cierra sola a los <?= (int) (\App\Services\MostradorService::INACTIVIDAD / 60) ?> minutos sin uso,
      y al tocar «Cambiar de usuario» queda lista para el siguiente.
    </p>
    <ol class="mb-0">
      <li>Habilitá acá el equipo, <strong>entrando desde ese mismo equipo</strong>.</li>
      <li>Cada persona se pone su PIN en <a href="<?= url('perfil/clave#pin') ?>">su perfil</a> (botón con su nombre, arriba).</li>
    </ol>
    <p class="small text-muted mt-2 mb-0">El PIN solo sirve en los equipos habilitados: desde cualquier otro lado se sigue entrando con usuario y contraseña.
      Después de <?= \App\Services\MostradorService::MAX_INTENTOS ?> PIN equivocados, esa persona queda bloqueada <?= \App\Services\MostradorService::MINUTOS_BLOQUEO ?> minutos en ese equipo.</p>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Este equipo</strong></div>
  <div class="card-body">
    <?php if ($esteEquipo): ?>
      <p class="mb-0 text-success"><?= icono('check-circle-fill') ?> Este navegador es el mostrador «<?= e($esteEquipo['nombre']) ?>».</p>
    <?php else: ?>
      <form action="<?= url('mostrador') ?>" method="POST" class="d-flex flex-wrap gap-2 align-items-end">
        <?= csrf_field() ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'nombre', 'etiqueta' => 'Nombre del equipo', 'columna' => 'mb-0 flex-grow-1 ancho-max-420',
          'atributos' => ['maxlength' => 60, 'required' => true, 'placeholder' => 'Ej: PC del mostrador'],
        ]) ?>
        <button type="submit" class="btn btn-seccion"><?= icono('grid-3x3-gap') ?> Usar este equipo como mostrador</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Equipos habilitados</strong></div>
  <div class="card-body">
    <?php if ($mostradores === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'display', 'texto' => 'Todavía no hay ningún equipo habilitado como mostrador.']) ?>
    <?php else: ?>
      <ul class="list-group">
        <?php foreach ($mostradores as $m): ?>
          <li class="list-group-item d-flex flex-wrap gap-2 align-items-center">
            <span class="me-auto">
              <strong><?= e($m['nombre']) ?></strong>
              <span class="small text-muted">· habilitado el <?= format_date($m['created_at']) ?><?= $m['activado_por'] ? ' por ' . e($m['activado_por']) : '' ?>
                · <?= $m['ultimo_uso'] ? 'último ingreso con PIN: ' . format_date($m['ultimo_uso'], 'd/m/Y H:i') : 'sin ingresos con PIN' ?></span>
            </span>
            <?= $view->partial('partials/delete_button', [
              'action' => "mostrador/{$m['id']}/quitar", 'label' => 'Quitar', 'icono' => 'x-circle',
              'confirm' => "¿Quitar «{$m['nombre']}» del modo mostrador? Ahí se va a entrar con usuario y contraseña.",
            ]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="small text-muted mt-3 mb-0">
      <?= $conPin === [] ? 'Nadie tiene PIN todavía.' : 'Tienen PIN: ' . e(implode(', ', array_column($conPin, 'nombre'))) . '.' ?>
    </p>
  </div>
</div>
