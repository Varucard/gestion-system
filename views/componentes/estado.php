<?php
/**
 * Badge con el estado de una orden o de un turno. Los colores viven en componentes.css
 * (clases estado-*), no en cada vista.
 *
 * @var \App\Enums\EstadoOrden|\App\Enums\EstadoTurno $estado
 */
?>
<span class="badge badge-estado estado-<?= e($estado->value) ?>"><?= e($estado->label()) ?></span>
