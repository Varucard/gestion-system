<?php
/**
 * Menú principal de computadora y tablet. Cada sección es un botón doble: el texto lleva
 * directo a su página principal (un clic) y la flecha abre el resto de sus páginas (en
 * computadoras también se abre al pasar el mouse). En el celular lo reemplaza partials/menu_movil.
 */
use App\Support\MenuPrincipal;

$seccion = current_section();
?>
<nav class="menu-principal d-none d-md-flex mb-2" aria-label="Menú principal">
  <a href="<?= url('/') ?>" class="btn btn-menu btn-menu-inicio <?= $seccion === '' ? 'activo' : '' ?>" title="Inicio" aria-label="Inicio"
    <?= $seccion === '' ? 'aria-current="page"' : '' ?>><?= icono('house-door') ?></a>
  <?php foreach (MenuPrincipal::secciones(auth()->esAdministrador()) as [$icono, $titulo, $color, $principal, $items]): ?>
    <?php // La sección en la que se está queda marcada (subrayado grueso), como en el celular. ?>
    <?php $activa = MenuPrincipal::contiene($items, $principal, $seccion); ?>
    <div class="btn-group menu-seccion <?= $titulo === 'Configuración' ? 'menu-seccion-final' : '' ?>">
      <a href="<?= url($principal) ?>" class="btn btn-menu btn-menu-<?= e($color) ?> <?= $activa ? 'activo' : '' ?>" <?= $activa ? 'aria-current="page"' : '' ?>><?= icono($icono) ?> <?= e($titulo) ?></a>
      <button class="btn btn-menu btn-menu-<?= e($color) ?> dropdown-toggle dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="visually-hidden">Más opciones de <?= e($titulo) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($items as $ruta => $etiqueta): ?>
          <li><a class="dropdown-item" href="<?= url($ruta) ?>"><?= e($etiqueta) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</nav>

<?php // Mientras se escribe, app.js muestra las primeras coincidencias debajo (patrón "combobox"). ?>
<form class="mb-3 position-relative buscador" action="<?= url('buscar') ?>" method="GET" role="search">
  <div class="input-group">
    <span class="input-group-text"><?= icono('search') ?></span>
    <input type="search" name="q" id="busqueda_rapida" class="form-control" placeholder="N° de serie, equipo, DNI, apellido u orden" aria-label="Buscar"
      autocomplete="off" role="combobox" aria-expanded="false" aria-controls="busqueda_sugerencias" aria-autocomplete="list"
      data-sugerencias-url="<?= e(url('buscar/sugerencias')) ?>"
      value="<?= e($seccion === 'buscar' ? (string) ($_GET['q'] ?? '') : '') ?>">
  </div>
  <ul class="list-group buscador-sugerencias" id="busqueda_sugerencias" role="listbox" aria-label="Sugerencias" hidden></ul>
</form>
