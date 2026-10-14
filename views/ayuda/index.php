<?php
/**
 * Ayuda: el manual de usuario (docs/manual/manual.html, ver App\Support\Manual). A la
 * izquierda el buscador y los capítulos; a la derecha el texto. Cada pantalla del sistema
 * enlaza a su capítulo (#id) con el botón de Ayuda del encabezado.
 *
 * @var list<array{id: string, titulo: string, html: string}> $capitulos
 * @var array{url: string, pantalla: string}|null $volver  pantalla de la que se vino
 */
?>
<?php if ($volver !== null): ?>
  <?php // Fija arriba mientras se lee: se vuelve sin tener que subir. También con Esc (ayuda.js). ?>
  <div class="ayuda-volver">
    <a href="<?= e($volver['url']) ?>" class="btn btn-primary btn-sm js-ayuda-volver"><?= icono('arrow-left') ?> Volver a <?= e($volver['pantalla']) ?></a>
    <span class="small text-muted d-none d-md-inline">o tocá <kbd>Esc</kbd></span>
  </div>
<?php endif; ?>
<div class="row g-4 ayuda">
  <aside class="col-lg-3">
    <div class="ayuda-indice">
      <label for="ayuda_buscar" class="visually-hidden">Buscar en el manual</label>
      <div class="input-group mb-2">
        <span class="input-group-text"><?= icono('search') ?></span>
        <input type="search" id="ayuda_buscar" class="form-control" placeholder="Buscar en el manual" autocomplete="off">
      </div>
      <p class="small text-muted mb-2" id="ayuda_resultado" aria-live="polite"></p>

      <?php // En el celular el índice va plegado, para que el texto quede a la vista. ?>
      <details class="ayuda-contenido" open>
        <summary class="fw-semibold mb-1">Contenido</summary>
        <nav aria-label="Capítulos del manual">
          <ol class="list-unstyled mb-0">
            <?php foreach ($capitulos as $capitulo): ?>
              <li data-capitulo="<?= e($capitulo['id']) ?>"><a href="#<?= e($capitulo['id']) ?>"><?= e($capitulo['titulo']) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </nav>
      </details>

      <a href="<?= url('ayuda/imprimir') ?>" class="btn btn-outline-secondary btn-sm mt-3" target="_blank" rel="noopener">
        <?= icono('printer') ?> Imprimir o guardar en PDF
      </a>
      <p class="small text-muted mt-2 mb-0">Versión <?= e(version_app()) ?></p>
    </div>
  </aside>

  <div class="col-lg-9">
    <?php // El HTML sale del manual, que es parte del código: no lleva datos de nadie. ?>
    <div class="manual">
      <?php foreach ($capitulos as $capitulo): ?>
        <section id="<?= e($capitulo['id']) ?>" class="manual-capitulo">
          <?= $capitulo['html'] ?>
          <a href="#contenido" class="small manual-arriba"><?= icono('arrow-up') ?> Volver arriba</a>
        </section>
      <?php endforeach; ?>
      <p class="text-muted" id="ayuda_sin_resultados" hidden>Nada del manual coincide con la búsqueda. Probá con otra palabra.</p>
    </div>
  </div>
</div>
