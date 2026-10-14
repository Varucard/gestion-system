<?php
/**
 * @var string $texto
 * @var array{clientes: list<array<string, mixed>>, equipos: list<array<string, mixed>>, ordenes: list<array<string, mixed>>} $resultados
 * @var int $total
 */
use App\Enums\EstadoOrden;
?>
<h4 class="mt-3">
  <?php if (mb_strlen($texto) < 2 && !ctype_digit($texto)): ?>
    Escribí al menos 2 caracteres para buscar.
  <?php else: ?>
    <?= $total ?> resultado<?= $total === 1 ? '' : 's' ?> para “<?= e($texto) ?>”
  <?php endif; ?>
</h4>

<?php // Sin resultados no es un callejón sin salida: se ofrece lo que probablemente se quería hacer. ?>
<?php if ($total === 0 && mb_strlen($texto) >= 2): ?>
  <?php $dni = preg_replace('/[.\s]/', '', $texto); ?>
  <div class="d-flex flex-wrap gap-2 mt-2">
    <?php if (preg_match('/^\d{7,8}$/', $dni)): ?>
      <a href="<?= url('recepcion?dni=' . urlencode($dni)) ?>" class="btn btn-primary"><?= icono('laptop-fill') ?> Recibir un equipo de un cliente nuevo (DNI <?= e($dni) ?>)</a>
    <?php else: ?>
      <a href="<?= url('recepcion') ?>" class="btn btn-primary"><?= icono('laptop-fill') ?> Llegó un equipo</a>
    <?php endif; ?>
    <a href="<?= url('clientes/crear') ?>" class="btn btn-outline-secondary"><?= icono('person-plus') ?> Nuevo cliente</a>
  </div>
  <p class="small text-muted mt-2">Se busca por n° de serie, marca y modelo, DNI, apellido, nombre o número de orden (por ejemplo, #12).</p>
<?php endif; ?>

<?php if ($resultados['ordenes'] !== []): ?>
  <div class="card mt-3">
    <div class="card-header"><strong>Órdenes</strong></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($resultados['ordenes'] as $o): ?>
        <li class="list-group-item">
          <a href="<?= url("ordenes/{$o['id']}") ?>">Orden #<?= (int) $o['id'] ?></a> · <?= e(equipo_texto($o)) ?> · <?= e($o['cliente']) ?>
          · <?= e(EstadoOrden::from($o['estado'])->label()) ?> · <?= importe($o['total']) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($resultados['equipos'] !== []): ?>
  <div class="card mt-3">
    <div class="card-header"><strong>Equipos</strong></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($resultados['equipos'] as $eq): ?>
        <li class="list-group-item">
          <a href="<?= url("equipos/{$eq['id']}") ?>"><?= e(equipo_texto($eq)) ?></a> · <?= e($eq['cliente']) ?>
          <?= $eq['estado'] !== 'activo' ? '<span class="badge bg-secondary">inactivo</span>' : '' ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($resultados['clientes'] !== []): ?>
  <div class="card mt-3">
    <div class="card-header"><strong>Clientes</strong></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($resultados['clientes'] as $c): ?>
        <li class="list-group-item">
          <a href="<?= url("clientes/{$c['id']}") ?>"><?= e("{$c['apellido']}, {$c['nombre']}") ?></a> · DNI <?= e($c['dni']) ?> · Tel. <?= e($c['telefono']) ?>
          <?= $c['estado'] !== 'activo' ? '<span class="badge bg-secondary">inactivo</span>' : '' ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
