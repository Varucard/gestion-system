<?php
/**
 * Campo de formulario: etiqueta, control y texto de ayuda, con el valor anterior
 * (old) si el formulario volvió con errores.
 *
 *   <?= $view->partial('componentes/campo', [
 *     'nombre' => 'telefono', 'etiqueta' => 'Teléfono *', 'tipo' => 'tel',
 *     'valor' => $cliente['telefono'] ?? '', 'columna' => 'col-md-6',
 *     'atributos' => ['required' => true, 'maxlength' => 10, 'readonly' => $cliente !== null],
 *     'ayuda' => '10 dígitos, sin 0 ni 15.',
 *   ]) ?>
 *
 * Desplegable con buscador (Tom Select), múltiple, con datos por opción:
 *
 *   'nombre' => 'servicio_id[]', 'id' => 'servicio_id', 'tipo' => 'select', 'buscable' => true,
 *   'placeholder' => 'Elegí servicios', 'valor' => [3, 5], 'atributos' => ['multiple' => true],
 *   'opciones' => [3 => ['texto' => 'Formateo ($ 15.000)', 'atributos' => ['data-precio' => 15000]]],
 *
 * @var string $nombre                        name del control
 * @var string|null $id                       id del control (por defecto, el nombre)
 * @var string $etiqueta                      admite HTML ya escapado en $etiquetaHtml
 * @var string|null $etiquetaHtml             etiqueta con HTML propio (p. ej. una aclaración en <small>)
 * @var string|null $tipo                     tipo de <input>, o "textarea" / "select" (por defecto "text")
 * @var mixed $valor                          valor actual (se usa si no hay uno anterior); lista en un select múltiple
 * @var bool|null $usarAnterior               false para no rellenar con old() (por defecto true)
 * @var array<string|int, string|array{texto: string, atributos?: array<string, mixed>}>|null $opciones
 * @var bool|null $buscable                   select con buscador (Tom Select)
 * @var string|null $placeholder              texto cuando no hay nada elegido (select buscable)
 * @var array<string, scalar|bool|null>|null $atributos  true = atributo sin valor; false/null = se omite
 * @var string|null $prefijo                  texto pegado a la izquierda del control (p. ej. "$")
 * @var string|null $ayuda                    texto chico debajo del control
 * @var string|null $columna                  si se indica, envuelve el campo en <div class="…">
 * @var string|null $clase                    clases extra del control
 */
$tipo ??= 'text';
$id ??= $nombre;
$atributos ??= [];
$clave = rtrim($nombre, '[]');
$valor = ($usarAnterior ?? true) ? old($clave, $valor ?? '') : ($valor ?? '');
$elegidos = array_map('strval', is_array($valor) ? $valor : [$valor]);
$claseControl = trim(($tipo === 'select' ? 'form-select' : 'form-control') . (!empty($buscable) ? ' js-buscable' : '') . ' ' . ($clase ?? ''));

if (!empty($buscable)) {
  $atributos['data-placeholder'] = $placeholder ?? '';
}

$html = function (array $atributos): string {
  $extra = '';
  foreach ($atributos as $atributo => $valorAtributo) {
    if ($valorAtributo === true) {
      $extra .= ' ' . e($atributo);
    } elseif ($valorAtributo !== false && $valorAtributo !== null) {
      $extra .= ' ' . e($atributo) . '="' . e($valorAtributo) . '"';
    }
  }

  return $extra;
};
$extra = $html($atributos);
?>
<?php if (!empty($columna)): ?><div class="<?= e($columna) ?>"><?php endif; ?>
  <label for="<?= e($id) ?>" class="form-label"><?= isset($etiquetaHtml) ? $etiquetaHtml : e($etiqueta) ?></label>
  <?php if (!empty($prefijo)): ?><div class="input-group"><span class="input-group-text"><?= e($prefijo) ?></span><?php endif; ?>
  <?php if ($tipo === 'textarea'): ?>
    <textarea class="<?= e($claseControl) ?>" id="<?= e($id) ?>" name="<?= e($nombre) ?>"<?= $extra ?>><?= e($valor) ?></textarea>
  <?php elseif ($tipo === 'select'): ?>
    <select class="<?= e($claseControl) ?>" id="<?= e($id) ?>" name="<?= e($nombre) ?>"<?= $extra ?>>
      <?php if (!empty($buscable) && empty($atributos['multiple'])): ?><option value=""></option><?php endif; ?>
      <?php foreach ($opciones ?? [] as $valorOpcion => $opcion): ?>
        <option value="<?= e($valorOpcion) ?>"<?= is_array($opcion) ? $html($opcion['atributos'] ?? []) : '' ?> <?= in_array((string) $valorOpcion, $elegidos, true) ? 'selected' : '' ?>><?= e(is_array($opcion) ? $opcion['texto'] : $opcion) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <input type="<?= e($tipo) ?>" class="<?= e($claseControl) ?>" id="<?= e($id) ?>" name="<?= e($nombre) ?>" value="<?= e($valor) ?>"<?= $extra ?>>
  <?php endif; ?>
  <?php if (!empty($prefijo)): ?></div><?php endif; ?>
  <?php if (!empty($ayuda)): ?>
    <small class="form-text text-muted"><?= e($ayuda) ?></small>
  <?php endif; ?>
<?php if (!empty($columna)): ?></div><?php endif; ?>
