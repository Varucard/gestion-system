<?php
/**
 * @var array<string, mixed> $valores
 * @var list<array{nombre: string, tamanio: int, fecha: string}> $archivos
 */
?>
<div class="form-check form-switch mb-2">
  <input class="form-check-input" type="checkbox" role="switch" id="habilitado" name="habilitado" value="1"
    <?= (session()->hasOldInput() ? old('habilitado') : $valores['habilitado']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="habilitado">Hacer un backup automático todos los días</label>
</div>
<div class="row">
  <?= $view->partial('componentes/campo', [
    'nombre' => 'hora', 'etiqueta' => 'A partir de las', 'tipo' => 'time', 'columna' => 'col-md-3 mb-3', 'valor' => $valores['hora'],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'conservar', 'etiqueta' => 'Conservar los últimos', 'tipo' => 'number', 'columna' => 'col-md-3 mb-3',
    'valor' => $valores['conservar'], 'atributos' => ['min' => 1, 'max' => 365],
  ]) ?>
</div>
<p class="small text-muted">
  Se guardan en <code>storage/backups</code> del servidor: la base de datos (<code>db_*.sql.gz</code>) y las imágenes y la
  configuración (<code>archivos_*.tar.gz</code>). Para restaurar: <code>scripts/restore.sh</code> (ver README).
  Conviene copiar periódicamente algún backup fuera del servidor.
</p>

<h5 class="mt-4">Backups disponibles</h5>
<?php if ($archivos === []): ?>
  <?= $view->partial('componentes/vacio', ['icono' => 'archive', 'texto' => 'Todavía no hay backups.']) ?>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Archivo</th><th>Fecha</th><th class="text-end">Tamaño</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($archivos as $a): ?>
          <tr>
            <td><code><?= e($a['nombre']) ?></code></td>
            <td><?= format_date($a['fecha'], 'd/m/Y H:i') ?></td>
            <td class="text-end"><?= e(number_format($a['tamanio'] / 1024, 0, ',', '.')) ?> KB</td>
            <td class="text-end"><a href="<?= url('configuracion/backups/descargar?archivo=' . rawurlencode($a['nombre'])) ?>" class="btn btn-sm btn-accion btn-outline-secondary" title="Descargar"><?= icono('download') ?><span class="btn-texto"> Descargar</span></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
