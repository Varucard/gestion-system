<?php
/** Fila del listado de clientes. @var array<string, mixed> $c @var array<int, float> $saldos */
$activo = $c['estado'] === 'activo';
$saldo = $saldos[(int) $c['id']] ?? null;

return [
  '<a href="' . e(url("clientes/{$c['id']}")) . '" class="fw-semibold">' . e("{$c['apellido']}, {$c['nombre']}") . '</a>'
    . '<div class="small text-muted">DNI ' . e($c['dni']) . ($c['email'] ? ' · ' . e($c['email']) : '') . '</div>',
  e($c['telefono']),
  $saldo ? '<span class="text-danger">' . importe($saldo) . '</span>' : '—',
  '<span class="badge bg-' . ($activo ? 'success' : 'secondary') . '">' . e($c['estado']) . '</span>',
  '<div class="acciones-fila">' . boton_accion("clientes/{$c['id']}", 'eye', 'Ver') . ' '
    . boton_accion("clientes/{$c['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') . ' '
    . $view->partial('partials/delete_button', [
      'action' => "clientes/{$c['id']}/estado",
      'label' => $activo ? 'Desactivar' : 'Activar',
      'class' => $activo ? 'btn-outline-warning' : 'btn-outline-success',
      'icono' => $activo ? 'slash-circle' : 'check-circle',
      'confirm' => null, // se hace al toque y se ofrece "Deshacer"
    ])
    . $view->partial('partials/delete_button', [
      'action' => "clientes/{$c['id']}/eliminar",
      'label' => 'Eliminar',
      'confirm' => "¿Eliminar al cliente {$c['nombre']} {$c['apellido']}? Esta acción no se puede deshacer.",
    ]) . '</div>',
];
