<?php
/**
 * Invitación a la encuesta de usabilidad, mientras haya una ronda abierta y el usuario no la
 * haya respondido. "Ahora no" la esconde hasta la próxima sesión.
 */
$encuestas = \App\Core\App::instance()->container->get(\App\Services\EncuestaService::class);
$usuario = auth()->id();
if ($usuario === null || !empty($_SESSION['encuesta_despues']) || current_section() === 'encuesta' || !$encuestas->pendientePara($usuario)) {
  return;
}
?>
<div class="alert alert-info d-flex flex-wrap gap-2 align-items-center" role="status">
  <span class="me-auto"><?= icono('chat-heart') ?> ¿Nos ayudás a mejorar el sistema? Son 10 preguntas cortas, un minuto.</span>
  <a href="<?= url('encuesta') ?>" class="btn btn-sm btn-primary">Responder</a>
  <form action="<?= url('encuesta/despues') ?>" method="POST" class="d-inline">
    <?= csrf_field() ?>
    <?php // La ruta sin la carpeta de instalación: redirect() la vuelve a agregar. ?>
    <input type="hidden" name="volver" value="<?= e('/' . current_section_path()) ?>">
    <button type="submit" class="btn btn-sm btn-link">Ahora no</button>
  </form>
</div>
