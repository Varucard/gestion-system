<?php
/**
 * Estado de confirmación del cliente y botones de aviso de un turno.
 *
 * @var array<string, mixed> $turno  fila de TurnoRepository (listado/detalle)
 * @var array{canal: bool, whatsapp: bool} $avisos
 * @var string $volver  "inicio" o "" (agenda)
 */
$canales = ['email' => 'email', 'whatsapp' => 'WhatsApp', 'sin_contacto' => 'sin contacto', 'error' => 'error', 'enviando' => 'enviando…'];
?>
<?php if ($turno['respuesta_cliente'] === 'confirmado'): ?>
  <div class="small text-success"><?= icono('check-lg') ?> Confirmado por el cliente <?= format_date($turno['respuesta_en'], 'd/m H:i') ?></div>
<?php elseif ($turno['respuesta_cliente'] === 'cancelado'): ?>
  <div class="small text-danger"><?= icono('x-lg') ?> Cancelado por el cliente <?= format_date($turno['respuesta_en'], 'd/m H:i') ?></div>
<?php elseif ($turno['confirmacion_enviada']): ?>
  <div class="small text-muted">Esperando confirmación (enviada <?= format_date($turno['confirmacion_enviada'], 'd/m H:i') ?>)</div>
<?php endif; ?>

<?php // Los avisos por email solo se ofrecen si el cliente tiene email; si no, se dice. ?>
<?php $conEmail = !empty($turno['cliente_email']); ?>
<div class="d-inline-flex flex-wrap gap-1 my-1 align-items-center">
  <?php if ($avisos['canal'] && !$conEmail): ?>
    <span class="small text-muted">Sin email<?= $avisos['whatsapp'] ? ': avisale por WhatsApp' : '' ?></span>
  <?php endif; ?>
  <?php if ($avisos['canal'] && $conEmail): ?>
    <form action="<?= url("turnos/{$turno['id']}/recordar") ?>" method="POST" class="d-inline" data-confirm="¿Enviar ahora el recordatorio al cliente por email?" data-confirm-aceptar="Sí, enviarlo">
      <?= csrf_field() ?>
      <input type="hidden" name="volver" value="<?= e($volver ?? '') ?>">
      <button type="submit" class="btn btn-sm btn-accion btn-outline-secondary" title="Enviar recordatorio por email"><?= icono('bell') ?><span class="btn-texto"> Recordar</span></button>
    </form>
    <?php if (!$turno['respuesta_cliente'] && ($volver ?? '') === ''): ?>
      <form action="<?= url("turnos/{$turno['id']}/confirmacion") ?>" method="POST" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-accion btn-outline-secondary" title="Reenviar el pedido de confirmación"><?= icono('envelope') ?><span class="btn-texto"> Pedir confirmación</span></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($avisos['whatsapp']): ?>
    <?php // Si el cliente todavía no confirmó, el mensaje lleva el link para confirmar o cancelar. ?>
    <?php $paraConfirmar = $turno['respuesta_cliente'] === null; ?>
    <form action="<?= url("turnos/{$turno['id']}/whatsapp") ?>" method="POST" target="<?= e(whatsapp_target_actual()) ?>" data-whatsapp class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="tipo" value="<?= $paraConfirmar ? 'confirmacion' : 'recordatorio' ?>">
      <button type="submit" class="btn btn-sm btn-accion btn-outline-success"
        title="<?= $paraConfirmar ? 'Abrir WhatsApp con el link para que confirme el turno' : 'Abrir WhatsApp con el recordatorio armado' ?>"><?= icono('whatsapp') ?><span class="btn-texto"> WhatsApp</span></button>
    </form>
  <?php endif; ?>
</div>

<?php if ($turno['recordatorio_enviado']): ?>
  <div class="small text-muted">Recordatorio: <?= e($canales[$turno['recordatorio_canal']] ?? (string) $turno['recordatorio_canal']) ?> · <?= format_date($turno['recordatorio_enviado'], 'd/m H:i') ?></div>
<?php endif; ?>
