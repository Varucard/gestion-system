<?php
/**
 * Pie de página: año, nombre del negocio (de Configuración > Sistema), quién lo desarrolló y
 * la versión (archivo VERSION). Va en todas las pantallas: con sesión, el login y las
 * públicas del cliente (portal, presupuesto, turno).
 *
 * @var bool|null $atajos  false en las pantallas del cliente: los atajos son del sistema interno
 */
$negocio = \App\Core\App::instance()->container->get(\App\Services\ConfiguracionService::class)->seccion('negocio')['nombre'];
?>
<footer class="pie">
  <p>&copy; <?= date('Y') ?> <?= e($negocio) ?> · Desarrollado por PC Fighter <span class="pie-version">· v<?= e(version_app()) ?></span>
    <?php if (($atajos ?? true) && auth()->user()): ?>
      <span class="d-none d-md-inline">· <button type="button" class="btn btn-link btn-sm p-0 align-baseline pie-atajos js-ver-atajos">Atajos de teclado <kbd>?</kbd></button></span>
    <?php endif; ?>
  </p>
</footer>
