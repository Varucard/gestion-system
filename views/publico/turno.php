<?php
/**
 * @var array<string, mixed> $turno
 * @var string $token
 * @var bool $admiteRespuesta
 */
use App\Enums\EstadoTurno;

$estado = EstadoTurno::from($turno['estado']);
?>
<div class="card">
  <div class="card-body p-4">
    <h2 class="h4">Hola <?= e(mb_convert_case(mb_strtolower((string) $turno['cliente_nombre']), MB_CASE_TITLE)) ?></h2>
    <p class="mb-3">Estos son los datos de tu turno:</p>

    <dl class="row mb-3">
      <dt class="col-sm-3">Fecha</dt><dd class="col-sm-9"><?= format_date($turno['fecha']) ?></dd>
      <dt class="col-sm-3">Hora</dt><dd class="col-sm-9"><?= e(substr($turno['hora'], 0, 5)) ?> hs</dd>
      <dt class="col-sm-3">Equipo</dt><dd class="col-sm-9"><?= e($turno['equipo']) ?></dd>
      <?php if ($turno['descripcion']): ?><dt class="col-sm-3">Motivo</dt><dd class="col-sm-9"><?= e($turno['descripcion']) ?></dd><?php endif; ?>
      <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><?= $view->partial('componentes/estado', ['estado' => $estado]) ?></dd>
    </dl>

    <?php if ($admiteRespuesta): ?>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($estado !== EstadoTurno::Confirmado): ?>
          <form action="<?= url("turno/{$token}/confirmar") ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success btn-lg"><?= icono('check-lg') ?> Confirmo que voy</button>
          </form>
        <?php endif; ?>
        <form action="<?= url("turno/{$token}/cancelar") ?>" method="POST" data-confirm="¿Seguro que querés cancelar el turno?">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-danger btn-lg"><?= icono('x-lg') ?> Cancelar turno</button>
        </form>
      </div>
      <?php if ($estado === EstadoTurno::Confirmado): ?>
        <p class="text-success mt-3 mb-0">Tu turno está confirmado. ¡Te esperamos!</p>
      <?php endif; ?>
    <?php endif; ?>

    <hr>
    <a href="<?= url('seguimiento') ?>">Consultar el estado de mi equipo</a>
  </div>
</div>
