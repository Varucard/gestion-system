<?php
/**
 * @var string|null $ronda
 * @var bool $pendiente
 * @var array<int, string> $preguntas
 */
?>
<?php if ($ronda === null || !$pendiente): ?>
  <div class="mt-3">
    <?= $view->partial('componentes/vacio', ['icono' => 'emoji-smile', 'texto' => $ronda === null ? 'No hay ninguna encuesta abierta ahora.' : 'Ya respondiste esta encuesta. ¡Gracias!']) ?>
  </div>
<?php else: ?>
  <form action="<?= url('encuesta') ?>" method="POST" class="mt-3 encuesta">
    <?= csrf_field() ?>
    <p>Son 10 frases sobre el sistema. Marcá qué tan de acuerdo estás con cada una, de <strong>1 (nada de acuerdo)</strong> a <strong>5 (muy de acuerdo)</strong>.
      No hay respuestas buenas ni malas: lo que importa es tu experiencia. Es anónima para el resto del equipo.</p>
    <?php foreach ($preguntas as $n => $pregunta): ?>
      <fieldset class="mb-3 pregunta-encuesta">
        <legend class="fs-6 mb-2"><strong><?= $n ?>.</strong> <?= e($pregunta) ?></legend>
        <div class="btn-group w-100 escala-sus" role="group">
          <?php for ($v = 1; $v <= 5; $v++): ?>
            <input type="radio" class="btn-check" name="p<?= $n ?>" id="p<?= $n ?>_<?= $v ?>" value="<?= $v ?>" required <?= (string) old("p{$n}") === (string) $v ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="p<?= $n ?>_<?= $v ?>" title="<?= $v === 1 ? 'Nada de acuerdo' : ($v === 5 ? 'Muy de acuerdo' : '') ?>"><?= $v ?></label>
          <?php endfor; ?>
        </div>
        <div class="d-flex justify-content-between small text-muted escala-encuesta"><span>Nada de acuerdo</span><span>Muy de acuerdo</span></div>
      </fieldset>
    <?php endforeach; ?>
    <?= $view->partial('componentes/campo', [
      'nombre' => 'comentario', 'etiqueta' => '¿Qué es lo que más te cuesta del sistema? (opcional)', 'tipo' => 'textarea', 'columna' => 'mb-3',
      'atributos' => ['rows' => 3, 'maxlength' => 2000],
    ]) ?>
    <button type="submit" class="btn btn-seccion btn-lg"><?= icono('send') ?> Enviar respuestas</button>
  </form>
<?php endif; ?>
