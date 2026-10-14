<?php
/**
 * Botón que abre WhatsApp con un aviso de la orden ya escrito, con su link (ver
 * OrdenController::whatsapp). Se abre en la pestaña de WhatsApp (o en la app de escritorio,
 * según Configuración › Avisos): la del sistema queda donde estaba.
 *
 * @var int $orden
 * @var string $tipo   presupuesto | presupuesto_modificado | listo | service
 * @var string $texto
 * @var string $clase  clases del botón (por defecto, ítem de un menú desplegable)
 */
?>
<form action="<?= url("ordenes/{$orden}/whatsapp/{$tipo}") ?>" method="POST" target="<?= e(whatsapp_target_actual()) ?>" data-whatsapp class="<?= isset($clase) ? 'd-inline' : '' ?>">
  <?= csrf_field() ?>
  <button type="submit" class="<?= e($clase ?? 'dropdown-item') ?>"><?= icono('whatsapp') ?> <?= e($texto) ?></button>
</form>
