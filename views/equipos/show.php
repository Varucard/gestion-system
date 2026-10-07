<?php
/**
 * @var array<string, mixed> $equipo
 * @var list<array<string, mixed>> $imagenes
 * @var list<array<string, mixed>> $ordenes
 * @var list<array<string, mixed>> $turnos
 * @var array<string, mixed>|null $proximoMantenimiento
 */
$dato = fn(?string $v) => $v !== null && $v !== '' ? e($v) : '—';
?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="mb-0"><?= e(equipo_texto($equipo, false)) ?>
      <?php if ($equipo['estado'] !== 'activo'): ?><span class="badge bg-secondary fs-6">inactivo</span><?php endif; ?>
    </h4>
    <div class="d-flex gap-2">
      <a href="<?= url('ordenes/crear?equipo_id=' . $equipo['id']) ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> Nueva orden</a>
      <?= boton_accion("equipos/{$equipo['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
    </div>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-2">Cliente</dt><dd class="col-sm-4"><a href="<?= url("clientes/{$equipo['cliente_id']}") ?>"><?= e($equipo['cliente']) ?></a></dd>
      <dt class="col-sm-2">N° de serie</dt><dd class="col-sm-4"><?= $dato($equipo['numero_serie']) ?></dd>
      <dt class="col-sm-2">Procesador</dt><dd class="col-sm-4"><?= $dato($equipo['procesador']) ?></dd>
      <dt class="col-sm-2">Memoria RAM</dt><dd class="col-sm-4"><?= $dato($equipo['memoria']) ?></dd>
      <dt class="col-sm-2">Almacenamiento</dt><dd class="col-sm-4"><?= $dato($equipo['almacenamiento']) ?></dd>
      <dt class="col-sm-2">Color</dt><dd class="col-sm-4"><?= $dato($equipo['color']) ?></dd>
      <dt class="col-sm-2">Próximo mantenimiento</dt>
      <dd class="col-sm-4">
        <?php if ($proximoMantenimiento): ?>
          <?= format_date($proximoMantenimiento['proximo_mantenimiento_fecha']) ?>
          <a class="small" href="<?= url("ordenes/{$proximoMantenimiento['orden_id']}") ?>">(orden #<?= (int) $proximoMantenimiento['orden_id'] ?>)</a>
        <?php else: ?>—<?php endif; ?>
      </dd>
      <dt class="col-sm-2">Observaciones</dt><dd class="col-sm-10"><?= nl2br($dato($equipo['detalle'])) ?></dd>
    </dl>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Imágenes</strong></div>
  <div class="card-body">
    <?php if ($imagenes !== []): ?>
      <div class="row g-3 mb-3">
        <?php foreach ($imagenes as $img): ?>
          <?php $src = url("equipos/{$equipo['id']}/imagenes/{$img['id']}"); ?>
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <a href="<?= $src ?>" target="_blank" rel="noopener">
                <img src="<?= $src ?>" alt="<?= e($img['descripcion'] ?? 'Imagen del equipo') ?>" class="card-img-top galeria-imagen" loading="lazy">
              </a>
              <div class="card-body p-2 small">
                <div><?= e($img['descripcion'] ?? '') ?></div>
                <div class="text-muted"><?= format_date($img['created_at'], 'd/m/Y H:i') ?></div>
                <?= $view->partial('partials/delete_button', [
                  'action' => "imagenes/{$img['id']}/eliminar", 'label' => 'Eliminar',
                  'class' => 'btn-outline-danger mt-1', 'confirm' => '¿Eliminar esta imagen?',
                ]) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form action="<?= url("equipos/{$equipo['id']}/imagenes") ?>" method="POST" enctype="multipart/form-data" class="row g-2">
      <?= csrf_field() ?>
      <div class="col-md-5">
        <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required aria-label="Imagen">
      </div>
      <div class="col-md-5">
        <input type="text" class="form-control" name="descripcion" maxlength="255" placeholder="Descripción (ej: estado al ingresar)" aria-label="Descripción de la imagen">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary w-100">Subir imagen</button>
      </div>
    </form>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Historial de órdenes</strong></div>
  <div class="card-body"><?= $view->partial('partials/historial_ordenes', ['ordenes' => $ordenes]) ?></div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Historial de turnos</strong></div>
  <div class="card-body"><?= $view->partial('partials/historial_turnos', ['turnos' => $turnos]) ?></div>
</div>
