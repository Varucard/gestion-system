<?php
/**
 * Menú principal de computadora y tablet: botones con submenú (en computadoras
 * también se abren al pasar el mouse). En el celular lo reemplaza partials/menu_movil.
 */
use App\Support\MenuPrincipal;

$seccion = current_section();
?>
<nav class="menu-principal d-none d-md-flex mb-2" aria-label="Menú principal">
  <a href="<?= url('/') ?>" class="btn btn-outline-secondary" title="Inicio" aria-label="Inicio"><?= icono('house-door') ?></a>
  <?php foreach (MenuPrincipal::secciones(auth()->esAdministrador()) as [$icono, $titulo, $color, $items]): ?>
    <div class="dropdown">
      <button class="btn btn-<?= $color ?> w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= icono($icono) ?> <?= e($titulo) ?></button>
      <ul class="dropdown-menu w-100">
        <?php foreach ($items as $ruta => $etiqueta): ?>
          <li><a class="dropdown-item" href="<?= url($ruta) ?>"><?= e($etiqueta) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</nav>

<form class="mb-3" action="<?= url('buscar') ?>" method="GET" role="search">
  <input type="search" name="q" class="form-control" placeholder="Buscar n° de serie, equipo, DNI, apellido u orden…" aria-label="Buscar"
    value="<?= e($seccion === 'buscar' ? (string) ($_GET['q'] ?? '') : '') ?>">
</form>
