<?php
/**
 * Menú del celular: barra inferior con los accesos de todos los días y un menú
 * lateral con todo lo demás. Va directo en <body>, fuera de la tarjeta principal:
 * dentro de un elemento con transform, lo "fijo" deja de estar fijo a la pantalla.
 */
use App\Support\MenuPrincipal;

$seccion = current_section();
?>
<nav class="barra-inferior d-md-none" aria-label="Accesos rápidos">
  <?php foreach (MenuPrincipal::accesos() as [$ruta, $icono, $etiqueta]): ?>
    <a href="<?= url($ruta) ?>" class="<?= $seccion === $ruta ? 'activo' : '' ?>" <?= $seccion === $ruta ? 'aria-current="page"' : '' ?>>
      <span class="icono"><?= icono($icono) ?></span><?= e($etiqueta) ?>
    </a>
  <?php endforeach; ?>
  <button type="button" data-bs-toggle="offcanvas" data-bs-target="#menu_movil" aria-controls="menu_movil">
    <span class="icono"><?= icono('list') ?></span>Menú
  </button>
</nav>

<div class="offcanvas offcanvas-end menu-movil d-md-none" tabindex="-1" id="menu_movil" aria-labelledby="menu_movil_titulo">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title h5" id="menu_movil_titulo">Menú</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?= $view->partial('partials/acciones_usuario', ['clase' => 'btn btn-outline-secondary']) ?>
    </div>
    <?php // Categorías plegables: solo queda abierta la de la sección en la que se está. ?>
    <div class="accordion accordion-flush" id="menu_movil_categorias">
      <?php foreach (MenuPrincipal::secciones(auth()->esAdministrador()) as $i => [$icono, $titulo, , $items]): ?>
        <?php $abierta = in_array($seccion, array_map(fn($ruta) => explode('/', $ruta)[0], array_keys($items)), true); ?>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button <?= $abierta ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse"
              data-bs-target="#menu_movil_<?= $i ?>" aria-expanded="<?= $abierta ? 'true' : 'false' ?>" aria-controls="menu_movil_<?= $i ?>">
              <?= icono($icono) ?>&nbsp; <?= e($titulo) ?>
            </button>
          </h3>
          <div id="menu_movil_<?= $i ?>" class="accordion-collapse collapse <?= $abierta ? 'show' : '' ?>" data-bs-parent="#menu_movil_categorias">
            <div class="list-group list-group-flush">
              <?php foreach ($items as $ruta => $etiqueta): ?>
                <a class="list-group-item list-group-item-action" href="<?= url($ruta) ?>"><?= e($etiqueta) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
