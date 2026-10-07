<?php
/**
 * <head> común a todos los layouts: metadatos, tema, estilos y app instalable.
 *
 * @var string $titulo título completo de la pestaña
 * @var string $manifiesto ruta del manifiesto de la app instalable (app o portal)
 * @var bool $privado true en páginas que no deben indexarse
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php if (!empty($privado)): ?><meta name="robots" content="noindex"><?php endif; ?>
<title><?= e($titulo) ?></title>
<script>
  // El tema se aplica antes de pintar, para que el modo oscuro no parpadee.
  (function () {
    let tema = 'light';
    try { tema = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (e) {}
    document.documentElement.dataset.bsTheme = tema;
  })();
</script>
<meta name="theme-color" content="#f8f9fa" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#111827" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" href="<?= asset('img/logo_64.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('img/icono-180.png') ?>">
<link rel="manifest" href="<?= url($manifiesto) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
