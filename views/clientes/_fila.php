<?php
/** Fila del listado de clientes. @var array<string, mixed> $c @var array<int, float> $saldos */
$activo = $c['estado'] === 'activo';
$saldo = $saldos[(int) $c['id']] ?? null;

return [
  e($c['nombre']),
  e($c['apellido']),
  e($c['dni']),
  e($c['telefono']),
  e($c['email'] ?? ''),
  e($c['direccion'] ?? ''),
  $saldo ? '<span class="text-danger">' . importe($saldo) . '</span>' : '—',
  '<span class="badge bg-' . ($activo ? 'success' : 'secondary') . '">' . e($c['estado']) . '</span>',
  '<div class="acciones-fila">' . boton_accion("clientes/{$c['id']}", 'eye', 'Ver') . ' '
    . boton_accion("clientes/{$c['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') . ' '
    . $view->partial('partials/delete_button', [
      'action' => "clientes/{$c['id']}/estado",
      'label' => $activo ? 'Desactivar' : 'Activar',
      'class' => $activo ? 'btn-outline-warning' : 'btn-outline-success',
      'icono' => $activo ? 'pause-circle' : 'play-circle',
      'confirm' => '¿' . ($activo ? 'Desactivar' : 'Activar') . " al cliente {$c['nombre']} {$c['apellido']}?",
    ])
    . $view->partial('partials/delete_button', [
      'action' => "clientes/{$c['id']}/eliminar",
      'label' => 'Eliminar',
      'confirm' => "¿Eliminar al cliente {$c['nombre']} {$c['apellido']}? Esta acción no se puede deshacer.",
    ]) . '</div>',
];
