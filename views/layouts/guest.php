<?php /** Layout de pantallas sin sesión (login, primer ingreso). @var string $content */ ?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?= $view->partial('partials/head', ['titulo' => ($title ?? '') . ' - Servicio Técnico', 'manifiesto' => 'app.webmanifest']) ?>
</head>

<body>
  <main class="container ancho-max-440 pagina-acceso">
    <div class="text-center mb-3">
      <img src="<?= asset('img/logo.png') ?>" alt="Logo del negocio" class="rounded-circle" width="110" height="110">
    </div>
    <div class="card">
      <div class="card-body p-4">
        <h1 class="h4 mb-3 text-center"><?= e($title ?? '') ?></h1>
        <?= $view->partial('partials/alerts') ?>
        <?= $content ?>
      </div>
    </div>
  </main>
  <script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
</body>

</html>
