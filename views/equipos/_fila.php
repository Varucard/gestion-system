<?php
/** Fila del listado de equipos. @var array<string, mixed> $v */
$activo = $v['estado'] === 'activo';

return [
  e($v['cliente']),
  e(\App\Enums\TipoEquipo::etiqueta($v['tipo'])),
  e($v['marca']),
  e($v['modelo']),
  $v['numero_serie'] !== null ? e($v['numero_serie']) : '—',
  '<span class="badge bg-' . ($activo ? 'success' : 'secondary') . '">' . e($v['estado']) . '</span>',
  '<div class="acciones-fila">' . boton_accion("equipos/{$v['id']}", 'eye', 'Ver') . ' '
    . boton_accion("equipos/{$v['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') . ' '
    . $view->partial('partials/delete_button', [
      'action' => "equipos/{$v['id']}/estado",
      'label' => $activo ? 'Desactivar' : 'Activar',
      'class' => $activo ? 'btn-outline-warning' : 'btn-outline-success',
      'icono' => $activo ? 'pause-circle' : 'play-circle',
      'confirm' => '¿' . ($activo ? 'Desactivar' : 'Activar') . ' el equipo ' . equipo_texto($v) . '?',
    ]) . '</div>',
];
