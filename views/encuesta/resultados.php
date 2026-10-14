<?php
/**
 * @var string|null $ronda
 * @var list<array<string, mixed>> $resultados
 * @var array<int, string> $preguntas
 */
use App\Services\EncuestaService;
?>
<div class="card mt-3">
  <div class="card-body">
    <p>
      La <strong>encuesta SUS</strong> mide qué tan fácil es usar el sistema: 10 preguntas que dan un puntaje de 0 a 100. El promedio de la industria
      es <?= (int) EncuestaService::PROMEDIO ?>. Sirve para comparar: abrí una ronda antes de un cambio y otra después.
    </p>
    <p class="small text-muted mb-3">
      Junto con la encuesta, lo que más enseña es ver a 3 a 5 personas del negocio haciendo tareas reales: con pocas personas aparecen casi
      todos los problemas. Quien instaló el sistema tiene el paso a paso para hacerlo.
    </p>
    <?php if ($ronda): ?>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="me-auto"><?= icono('broadcast') ?> Ronda abierta: <strong><?= e($ronda) ?></strong>. Cada usuario ve la invitación una vez por sesión hasta que responde.</span>
        <a href="<?= url('encuesta') ?>" class="btn btn-outline-secondary">Responderla</a>
        <form action="<?= url('usabilidad/cerrar') ?>" method="POST" class="d-inline" data-confirm="¿Cerrar la ronda «<?= e($ronda) ?>»? Ya no se invita a responder." data-confirm-aceptar="Sí, cerrarla">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-danger">Cerrar la ronda</button>
        </form>
      </div>
    <?php else: ?>
      <form action="<?= url('usabilidad/abrir') ?>" method="POST" class="d-flex flex-wrap gap-2 align-items-end">
        <?= csrf_field() ?>
        <?= $view->partial('componentes/campo', [
          'nombre' => 'ronda', 'etiqueta' => 'Nombre de la ronda', 'columna' => 'mb-0 flex-grow-1 ancho-max-420',
          'atributos' => ['maxlength' => 60, 'required' => true, 'placeholder' => 'Ej: Antes de los cambios de octubre'],
        ]) ?>
        <button type="submit" class="btn btn-seccion"><?= icono('broadcast') ?> Abrir una ronda</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($resultados === []): ?>
  <div class="mt-3"><?= $view->partial('componentes/vacio', ['icono' => 'clipboard-data', 'texto' => 'Todavía no hay respuestas.']) ?></div>
<?php else: ?>
  <div class="row g-3 mt-1">
    <?php foreach ($resultados as $r): ?>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between"><strong><?= e($r['ronda']) ?></strong>
            <span class="small text-muted"><?= (int) $r['respuestas'] ?> <?= (int) $r['respuestas'] === 1 ? 'respuesta' : 'respuestas' ?></span></div>
          <div class="card-body">
            <div class="numero-valor"><?= e(number_format($r['promedio'], 1, ',', '.')) ?> <span class="fs-6 text-muted">/ 100</span></div>
            <p class="mb-2"><strong><?= e($r['lectura']) ?></strong>
              <span class="small text-muted">(de <?= e(number_format((float) $r['minimo'], 1, ',', '.')) ?> a <?= e(number_format((float) $r['maximo'], 1, ',', '.')) ?>)</span></p>
            <details>
              <summary class="small">Promedio de cada pregunta (1 a 5)</summary>
              <ol class="small mt-2 mb-0">
                <?php foreach ($preguntas as $n => $pregunta): ?>
                  <li><?= e($pregunta) ?> <strong><?= e(number_format($r['porPregunta'][$n], 1, ',', '.')) ?></strong></li>
                <?php endforeach; ?>
              </ol>
              <p class="small text-muted mt-2 mb-0">En las impares conviene que sea alto; en las pares, bajo.</p>
            </details>
            <?php if ($r['comentarios'] !== []): ?>
              <h3 class="h6 mt-3">Lo que más cuesta</h3>
              <ul class="small mb-0">
                <?php foreach ($r['comentarios'] as $comentario): ?><li><?= nl2br(e($comentario)) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
