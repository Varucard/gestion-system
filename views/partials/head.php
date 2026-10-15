<?php
/**
 * <head> común a todos los layouts: metadatos, tema, estilos y app instalable.
 *
 * @var string $titulo título completo de la pestaña
 * @var string $manifiesto ruta del manifiesto de la app instalable (app o portal)
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php // Es un sistema interno: ninguna página va a los buscadores (también lo dice el .htaccess). ?>
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?></title>
<script>
  // El tema se aplica antes de pintar, para que el modo oscuro no parpadee.
  (function () {
    let tema = 'dark';
    // Por defecto, el oscuro: es el de la marca PC Fighter.
    try { tema = localStorage.getItem('theme') || 'dark'; } catch (e) {}
    document.documentElement.dataset.bsTheme = tema;
    // Tamaño de letra elegido con A− / A+ (base.css): también antes de pintar, para que no salte.
    try { const letra = localStorage.getItem('letra'); if (letra) document.documentElement.dataset.letra = letra; } catch (e) {}
  })();
</script>
<meta name="theme-color" content="#0a0820">
<link rel="icon" type="image/png" href="<?= asset('img/logo_64.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('img/icono-180.png') ?>">
<link rel="manifest" href="<?= url($manifiesto) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<?php
/*
 * Estilos por capas (@layer): el orden de abajo decide quién gana, sin importar la especificidad.
 * Se importan acá y no desde un app.css para que cada archivo lleve su versión (asset()): si no,
 * después de cambiar un estilo, la PC del mostrador o la app instalada seguían mostrando el viejo.
 *
 *   vendor       Bootstrap, Bootstrap Icons, DataTables y Tom Select, tal cual vienen.
 *   tokens       Colores, radios y sombras. El ÚNICO lugar con valores de color.
 *   base         Elementos sueltos (tipografía, foco).
 *   componentes  Piezas propias: encabezado, menú, tarjetas, botones, estados.
 *   librerias    Ajustes de DataTables y Tom Select al tema.
 *   paginas      Lo que es propio de una sola pantalla.
 *   utilidades   Clases chicas de una sola propiedad.
 */
$capas = [
  ['vendor', 'vendor/bootstrap.min.css'], ['vendor', 'vendor/dataTables.bootstrap5.min.css'],
  ['vendor', 'vendor/responsive.bootstrap5.min.css'], ['vendor', 'vendor/tom-select.bootstrap5.min.css'],
  ['vendor', 'vendor/bootstrap-icons/bootstrap-icons.min.css'],
  ['tokens', 'css/tokens.css'], ['base', 'css/base.css'], ['componentes', 'css/componentes.css'],
  ['librerias', 'css/librerias.css'], ['paginas', 'css/paginas.css'], ['utilidades', 'css/utilidades.css'],
];
?>
<style>
  @layer vendor, tokens, base, componentes, librerias, paginas, utilidades;
<?php foreach ($capas as [$capa, $archivo]): ?>
  @import url("<?= e(asset($archivo)) ?>") layer(<?= $capa ?>);
<?php endforeach; ?>
</style>
