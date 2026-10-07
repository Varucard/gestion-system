<?php
/** @var array<string, mixed> $valores */
use App\Services\ConfiguracionService;

$campos = [
  'Email de confirmación (se envía al agendar)' => [
    'email_confirmacion_asunto' => ['Asunto', 1],
    'email_confirmacion' => ['Texto (debe incluir {link_turno})', 8],
  ],
  'Email de recordatorio (día hábil anterior)' => [
    'email_recordatorio_asunto' => ['Asunto', 1],
    'email_recordatorio' => ['Texto', 8],
  ],
  'Email del presupuesto (con el PDF adjunto)' => [
    'email_presupuesto_asunto' => ['Asunto', 1],
    'email_presupuesto' => ['Texto', 7],
  ],
  'Email de presupuesto modificado (si cambia uno ya aceptado)' => [
    'email_presupuesto_modificado_asunto' => ['Asunto', 1],
    'email_presupuesto_modificado' => ['Texto (debe incluir {link_presupuesto})', 8],
  ],
  'Email de equipo listo (al finalizar la orden)' => [
    'email_listo_asunto' => ['Asunto', 1],
    'email_listo' => ['Texto', 8],
  ],
  'Email de aviso de próximo mantenimiento' => [
    'email_mantenimiento_asunto' => ['Asunto', 1],
    'email_mantenimiento' => ['Texto', 7],
  ],
  'WhatsApp' => [
    'whatsapp_recordatorio' => ['Recordatorio (botón manual)', 3],
    'whatsapp_confirmacion' => ['Confirmación (para cuando se active WhatsApp Business)', 3],
    'whatsapp_presupuesto' => ['Presupuesto (para cuando se active WhatsApp Business)', 3],
    'whatsapp_presupuesto_modificado' => ['Presupuesto modificado (para cuando se active WhatsApp Business)', 3],
    'whatsapp_listo' => ['Equipo listo (para cuando se active WhatsApp Business)', 3],
    'whatsapp_mantenimiento' => ['Próximo mantenimiento (para cuando se active WhatsApp Business)', 3],
  ],
];
?>
<?php $codigos = fn(array $variables) => implode(' ', array_map(fn($v) => '<code>{' . e($v) . '}</code>', $variables)); ?>
<p class="text-muted mb-1">Variables en todos los mensajes: <?= $codigos(ConfiguracionService::VARIABLES_GENERALES) ?></p>
<p class="text-muted mb-1">Solo en confirmación y recordatorio de turnos: <?= $codigos(ConfiguracionService::VARIABLES_TURNO) ?></p>
<p class="text-muted">Solo en presupuesto, equipo listo y próximo mantenimiento: <?= $codigos(ConfiguracionService::VARIABLES_ORDEN) ?></p>
<?php foreach ($campos as $titulo => $grupo): ?>
  <h5 class="mt-3"><?= e($titulo) ?></h5>
  <?php foreach ($grupo as $campo => [$etiqueta, $filas]): ?>
    <div class="mb-3">
      <label for="<?= $campo ?>" class="form-label"><?= e($etiqueta) ?></label>
      <?php if ($filas === 1): ?>
        <input type="text" class="form-control" id="<?= $campo ?>" name="<?= $campo ?>" required value="<?= e(old($campo, $valores[$campo])) ?>">
      <?php else: ?>
        <textarea class="form-control" id="<?= $campo ?>" name="<?= $campo ?>" rows="<?= $filas ?>" required><?= e(old($campo, $valores[$campo])) ?></textarea>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
