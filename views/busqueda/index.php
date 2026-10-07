<?php
/**
 * @var string $texto
 * @var array{clientes: list<array<string, mixed>>, equipos: list<array<string, mixed>>, ordenes: list<array<string, mixed>>} $resultados
 * @var int $total
 */
use App\Enums\EstadoOrden;
?>
<h4 class="mt-3">
  <?php if (mb_strlen($texto) < 2): ?>
    Escribí al menos 2 caracteres para buscar.
  <?php else: ?>
    <?= $total ?> resultado<?= $total === 1 ? '' : 's' ?> para “<?= e($texto) ?>”
  <?php endif; ?>
</h4>

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
