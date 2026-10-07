<?php
/**
 * Selector de filtro para tablas paginadas en el servidor.
 *
 * @var string $id        id del formulario (se referencia con data-filtros)
 * @var array<string, string> $opciones  valor => etiqueta (la primera es "todos")
 * @var string $nombre    nombre del parámetro
 * @var string $etiqueta
 */
?>
<form id="<?= e($id) ?>" class="d-flex align-items-center gap-2">
  <label for="<?= e($id) ?>_<?= e($nombre) ?>" class="small text-muted text-nowrap"><?= e($etiqueta) ?></label>
  <select id="<?= e($id) ?>_<?= e($nombre) ?>" name="<?= e($nombre) ?>" class="form-select form-select-sm">
    <?php foreach ($opciones as $valor => $texto): ?>
      <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
    <?php endforeach; ?>
  </select>
</form>
