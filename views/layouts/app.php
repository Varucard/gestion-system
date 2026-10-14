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

<?php $ayuda = url_ayuda($title ?? ''); // el botón de Ayuda, el del menú del celular y la tecla A ?>
<?php // Atajos de teclado (app.js): tecla => [destino, descripción]. "?" muestra la lista. ?>
<?php $atajos = ['n' => [url('recepcion'), 'Llegó un equipo'], 't' => [url('turnos/crear'), 'Nuevo turno'], 'o' => [url('ordenes'), 'Ver órdenes'], 'i' => [url('/'), 'Ir al inicio'], 'a' => [$ayuda, 'Ayuda de esta pantalla']]; ?>
<body class="con-barra-inferior" data-seccion="<?= e(current_section()) ?>" data-atajos="<?= e(json_encode($atajos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
  data-volvio-con-errores="<?= session()->hasOldInput() ? '1' : '0' ?>"
  <?php // El pedido anterior salió bien (dejó un aviso de éxito): app.js borra el borrador de ese formulario. ?>
  <?php $anteriores = session()->messages(); ?>
  data-exito="<?= !empty($anteriores['success']) || !empty($anteriores['deshacer']) || !empty($anteriores['aviso']) ? '1' : '0' ?>"
  <?= auth()->enMostrador() ? 'data-mostrador="' . \App\Services\MostradorService::INACTIVIDAD . '" data-sesion="' . e(url('sesion')) . '"' : '' ?>>
  <?php // Con teclado, el primer Tab ofrece saltar el menú y el buscador. ?>
  <a href="#contenido" class="visually-hidden-focusable saltar-al-contenido">Saltar al contenido</a>
  <div class="container app-marco my-md-3">
    <div class="card">
      <header class="card-header app-encabezado d-flex align-items-center gap-3 flex-wrap">
        <a href="<?= url('/') ?>" class="app-logo" title="Ir al inicio"><img src="<?= asset('img/logo.png') ?>" alt="Inicio" class="rounded-circle" width="48" height="48"></a>
        <?php // El título es solo el nombre de la pantalla: el logo y los botones van al lado, no adentro. ?>
        <h1 class="mb-0"><?= e($title ?? '') ?></h1>
        <div class="ms-auto d-none d-md-flex align-items-center gap-2 flex-wrap">
          <?= $view->partial('partials/acciones_usuario', ['clase' => 'btn btn-sm btn-encabezado', 'ayuda' => $ayuda]) ?>
        </div>
      </header>

      <div class="card-body">
        <?= $view->partial('partials/navbar') ?>
        <main id="contenido" tabindex="-1">
          <?= $view->partial('partials/alerts') ?>
          <?= $view->partial('partials/invitacion_encuesta') ?>
          <?= $content ?>
        </main>
      </div>
    </div>
    <?= $view->partial('partials/pie') ?>
  </div>

  <?= $view->partial('partials/menu_movil', ['ayuda' => $ayuda]) ?>

  <script src="<?= asset('vendor/jquery.min.js') ?>"></script>
  <script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset('vendor/dataTables.responsive.min.js') ?>"></script>
  <script src="<?= asset('vendor/responsive.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset('vendor/tom-select.complete.min.js') ?>"></script>
  <script src="<?= asset('js/reglas.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
  <?php foreach ($view->scripts() as $script): ?>
    <script src="<?= asset(str_starts_with($script, 'vendor/') ? $script : 'js/' . $script) ?>"></script>
  <?php endforeach; ?>
</body>

</html>
