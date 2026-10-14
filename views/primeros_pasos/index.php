<?php
/** @var list<array{clave: string, titulo: string, para: string, ruta: string, boton: string, hecho: bool, opcional: bool}> $pasos */
$hechos = count(array_filter($pasos, fn($p) => $p['hecho']));
?>
<div class="card mt-3">
  <div class="card-body">
    <p class="mb-2">Lo que conviene dejar cargado para empezar. Se tilda solo a medida que lo vas haciendo.</p>
    <progress class="w-100 avance-pasos" value="<?= $hechos ?>" max="<?= count($pasos) ?>" aria-label="Avance de los primeros pasos"></progress>
    <p class="small text-muted mb-0"><?= $hechos ?> de <?= count($pasos) ?> listos</p>
  </div>
</div>

<ol class="list-group list-group-numbered mt-3 primeros-pasos">
  <?php foreach ($pasos as $p): ?>
    <li class="list-group-item d-flex flex-wrap gap-3 align-items-center <?= $p['hecho'] ? 'paso-hecho' : '' ?>" id="<?= e($p['clave']) ?>">
      <div class="me-auto">
        <strong><?= e($p['titulo']) ?></strong>
        <?php if ($p['opcional']): ?><span class="badge text-bg-light border">opcional</span><?php endif; ?>
        <?php if ($p['hecho']): ?><span class="text-success"><?= icono('check-circle-fill') ?> Listo</span><?php endif; ?>
        <div class="small text-muted"><?= e($p['para']) ?></div>
      </div>
      <?php if ($p['clave'] === 'marcas'): ?>
        <?php // Un solo botón, como los demás pasos: sin marcas, cargar las comunes; con marcas, verlas (y, chico, sumar las comunes que falten). ?>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php if ($p['hecho']): ?>
            <a href="<?= url('marcas') ?>" class="btn btn-outline-secondary">Ver</a>
          <?php endif; ?>
          <form action="<?= url('primeros-pasos/marcas') ?>" method="POST" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn <?= $p['hecho'] ? 'btn-link btn-sm' : 'btn-seccion' ?>"><?= icono('magic') ?> <?= $p['hecho'] ? 'Sumar las más comunes que falten' : 'Cargar las marcas más comunes' ?></button>
          </form>
        </div>
      <?php else: ?>
        <a href="<?= url($p['ruta']) ?>" class="btn <?= $p['hecho'] ? 'btn-outline-secondary' : 'btn-seccion' ?>"><?= e($p['hecho'] ? 'Ver' : $p['boton']) ?></a>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>

<p class="small text-muted mt-3">¿Dudas con alguna pantalla? Tocá <strong>Ayuda</strong> arriba a la derecha: abre el manual en la parte que la explica.</p>
