<?php
/**
 * Layout de las páginas públicas para clientes (sin menú interno).
 *
 * @var string $content
 * @var array<string, string> $negocio
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?= $view->partial('partials/head', ['titulo' => ($title ?? '') . ' - ' . $negocio['nombre'], 'manifiesto' => 'portal.webmanifest', 'privado' => true]) ?>
</head>

<body>
  <main class="container my-4 ancho-max-820">
    <header class="d-flex align-items-center gap-3 mb-4">
      <img src="<?= asset('img/logo.png') ?>" alt="" class="rounded-circle" width="72" height="72">
      <div>
        <h1 class="h3 mb-0"><?= e($negocio['nombre']) ?></h1>
        <div class="text-muted"><?= e($title ?? '') ?></div>
      </div>
    </header>

    <?= $view->partial('partials/alerts') ?>
    <?= $content ?>

    <footer class="text-center text-muted small mt-5">
      <?= e($negocio['direccion']) ?> · Tel. <?= e($negocio['telefono']) ?>
      · <a href="<?= e(whatsapp_url($negocio['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a>
      · <a href="mailto:<?= e($negocio['email']) ?>"><?= e($negocio['email']) ?></a>
    </footer>
  </main>
  <script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
</body>

</html>
