<?php
/** Fila de la auditoría (tabla paginada en el servidor). @var array<string, mixed> $a */
$enlace = url_entidad($a['entidad'], $a['entidad_id'] !== null ? (int) $a['entidad_id'] : null);
$datos = $a['datos'] ? json_decode($a['datos'], true) : null;

return [
  format_date($a['created_at'], 'd/m/Y H:i:s'),
  e($a['usuario_nombre'] ?? '—') . ($a['ip'] ? '<div class="small text-muted">' . e($a['ip']) . '</div>' : ''),
  '<code>' . e($a['accion']) . '</code>',
  $enlace ? '<a href="' . e($enlace) . '">' . e($a['entidad']) . ' #' . (int) $a['entidad_id'] . '</a>' : e($a['entidad']),
  e($a['descripcion']),
  $datos ? '<pre class="small mb-0 pre-ajustado">' . e(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>' : '',
];
