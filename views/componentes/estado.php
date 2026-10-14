<?php
/**
 * Badge con el estado de una orden o de un turno. Los colores viven en componentes.css
 * (clases estado-*), no en cada vista.
 *
 * @var \App\Enums\EstadoOrden|\App\Enums\EstadoTurno $estado
 * @var bool|null $entregado  orden lista que el cliente ya retiró: se muestra «Entregado»
 */
$entregado = !empty($entregado) && $estado === \App\Enums\EstadoOrden::Finalizado;
?>
<span class="badge badge-estado estado-<?= $entregado ? 'entregado' : e($estado->value) ?>"><?= $entregado ? 'Entregado' : e($estado->label()) ?></span>
