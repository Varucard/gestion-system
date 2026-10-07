<?php
/**
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $ordenes
 * @var list<array<string, mixed>> $turnos
 * @var float $saldo
 */
$activo = $cliente['estado'] === 'activo';
?>
<div class="row g-3 mt-1">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body text-center">
        <?php if ($cliente['foto']): ?>
          <img src="<?= url("clientes/{$cliente['id']}/foto") ?>" alt="Foto de <?= e($cliente['nombre']) ?>" class="rounded-circle mb-3 foto-cliente">
        <?php else: ?>
          <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-3 foto-cliente foto-cliente-vacia">
            <?= e(mb_substr($cliente['nombre'], 0, 1) . mb_substr($cliente['apellido'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <h4 class="mb-1"><?= e("{$cliente['apellido']}, {$cliente['nombre']}") ?></h4>
        <span class="badge bg-<?= $activo ? 'success' : 'secondary' ?>"><?= e($cliente['estado']) ?></span>
        <?php if ($saldo > 0): ?>
          <span class="badge bg-danger">Debe <?= importe($saldo) ?></span>
        <?php else: ?>
          <span class="badge bg-light text-dark">Sin deuda</span>
        <?php endif; ?>

        <form action="<?= url("clientes/{$cliente['id']}/foto") ?>" method="POST" enctype="multipart/form-data" class="mt-3">
          <?= csrf_field() ?>
          <div class="input-group input-group-sm">
            <input type="file" class="form-control" name="foto" accept="image/jpeg,image/png,image/webp" required aria-label="Foto del cliente">
            <button type="submit" class="btn btn-outline-primary">Subir foto</button>
          </div>
        </form>
        <?php if ($cliente['foto']): ?>
          <div class="mt-2">
            <?= $view->partial('partials/delete_button', [
              'action' => "clientes/{$cliente['id']}/foto/eliminar", 'label' => 'Quitar foto',
              'class' => 'btn-outline-danger', 'confirm' => '¿Quitar la foto del cliente?',
            ]) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Datos</strong>
        <?= boton_accion("clientes/{$cliente['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') ?>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3">DNI</dt><dd class="col-sm-9"><?= e($cliente['dni']) ?></dd>
          <dt class="col-sm-3">Teléfono</dt>
          <dd class="col-sm-9">
            <?= e($cliente['telefono']) ?>
            <a href="<?= e(whatsapp_url($cliente['telefono'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-accion btn-outline-success ms-2" title="WhatsApp"><?= icono('whatsapp') ?><span class="btn-texto"> WhatsApp</span></a>
          </dd>
          <dt class="col-sm-3">Email</dt>
          <dd class="col-sm-9"><?= $cliente['email'] ? '<a href="mailto:' . e($cliente['email']) . '">' . e($cliente['email']) . '</a>' : '—' ?></dd>
          <dt class="col-sm-3">Dirección</dt><dd class="col-sm-9"><?= e($cliente['direccion'] ?? '—') ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Equipos</strong>
    <a href="<?= url('equipos/crear?cliente_id=' . $cliente['id']) ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> Agregar equipo</a>
  </div>
  <div class="card-body">
    <?php if ($equipos === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'car-front', 'texto' => 'Sin equipos registrados.']) ?>
    <?php else: ?>
      <div class="row g-2">
        <?php foreach ($equipos as $eq): ?>
          <div class="col-md-4">
            <a href="<?= url("equipos/{$eq['id']}") ?>" class="card text-decoration-none h-100 <?= $eq['estado'] !== 'activo' ? 'opacity-50' : '' ?>">
              <div class="card-body py-2">
                <strong><?= e(\App\Enums\TipoEquipo::etiqueta($eq['tipo'])) ?></strong> · <?= e("{$eq['marca']} {$eq['modelo']}") ?>
                <div class="small text-muted"><?= $eq['numero_serie'] ? 'S/N ' . e($eq['numero_serie']) : 'Sin n° de serie' ?><?= $eq['estado'] !== 'activo' ? ' · inactivo' : '' ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Historial de órdenes</strong></div>
  <div class="card-body"><?= $view->partial('partials/historial_ordenes', ['ordenes' => $ordenes]) ?></div>
</div>

<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Historial de turnos</strong>
    <a href="<?= url('turnos/crear?cliente_id=' . $cliente['id']) ?>" class="btn btn-sm btn-seccion"><?= icono('plus-lg') ?> Agendar turno</a>
  </div>
  <div class="card-body"><?= $view->partial('partials/historial_turnos', ['turnos' => $turnos]) ?></div>
</div>
