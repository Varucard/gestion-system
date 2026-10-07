<?php
/**
 * Layout principal.
 *
 * El color del encabezado sale de la sección (data-seccion en <body>, ver tokens.css).
 *
 * @var string $content  HTML de la vista
 * @var string $title    Título de la página
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?= $view->partial('partials/head', ['titulo' => ($title ?? 'Inicio') . ' - Servicio Técnico', 'manifiesto' => 'app.webmanifest']) ?>
  <meta name="csrf-token" content="<?= e(session()->csrfToken()) ?>">
</head>

<body class="con-barra-inferior" data-seccion="<?= e(current_section()) ?>">
  <div class="container app-marco my-md-3">
    <div class="card">
      <header class="card-header app-encabezado">
        <h1 class="mb-0 d-flex align-items-center gap-3 flex-wrap">
          <?= e($title ?? '') ?>
          <img src="<?= asset('img/logo.png') ?>" alt="Logo del negocio" class="rounded-circle" width="80" height="80">
          <span class="ms-auto d-none d-md-flex align-items-center gap-2 flex-wrap fs-6">
            <?= $view->partial('partials/acciones_usuario', ['clase' => 'btn btn-sm btn-encabezado']) ?>
          </span>
        </h1>
      </header>

      <div class="card-body">
        <?= $view->partial('partials/navbar') ?>
        <?= $view->partial('partials/alerts') ?>
        <?= $content ?>
      </div>
    </div>
  </div>

  <?= $view->partial('partials/menu_movil') ?>

  <script src="<?= asset('vendor/jquery.min.js') ?>"></script>
  <script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.responsive.min.js') ?>"></script>
  <script src="<?= asset('vendor/responsive.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset('vendor/tom-select.complete.min.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
  <?php foreach ($view->scripts() as $script): ?>
    <script src="<?= asset('js/' . $script) ?>"></script>
  <?php endforeach; ?>
</body>

</html>
