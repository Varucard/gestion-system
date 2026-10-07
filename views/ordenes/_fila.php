<?php
/** Fila del listado de órdenes (paginado en el servidor). @var array<string, mixed> $o @var list<\App\Enums\EstadoOrden> $estados */
use App\Enums\EstadoOrden;
use App\Services\OrdenService;

$estado = EstadoOrden::from($o['estado']);
$opciones = implode('', array_map(
  fn(EstadoOrden $op) => '<option value="' . e($op->value) . '" ' . selected($op === $estado) . '>' . e($op->label()) . '</option>',
  $estados
));

return [
  (int) $o['id'],
  e($o['cliente']),
  e($o['equipo']) . ($o['tecnico'] ? '<div class="small text-muted">' . icono('wrench') . ' ' . e($o['tecnico']) . '</div>' : ''),
  e($o['servicios'] ?? '') . ($o['repuestos'] ? '<div class="small text-muted mt-1"><strong>Repuestos:</strong> ' . e($o['repuestos']) . '</div>' : '')
    . ($o['presupuesto_respuesta'] ? '<div class="small ' . ($o['presupuesto_respuesta'] === 'aceptado' ? 'text-success">' . icono('check-lg') . ' Presupuesto aceptado' : 'text-danger">' . icono('x-lg') . ' Presupuesto rechazado') . '</div>' : ''),
  importe($o['total']),
  match (true) {
    $estado === EstadoOrden::Cancelado => '—',
    (float) $o['saldo'] > 0 => '<span class="text-danger">' . importe($o['saldo']) . '</span>',
    default => '<span class="badge bg-success">Pagada</span>',
  },
  format_date($o['created_at']),
  '<select class="form-select form-select-sm select-estado estado-orden-select estado-' . e($estado->value) . '" data-url="' . e(url("ordenes/{$o['id']}/estado")) . '" aria-label="Estado de la orden ' . (int) $o['id'] . '">' . $opciones . '</select>',
  boton_accion("ordenes/{$o['id']}", 'eye', 'Ver') . ' '
    . (OrdenService::editable($estado) ? boton_accion("ordenes/{$o['id']}/editar", 'pencil', 'Editar', 'btn-outline-primary') : ''),
];
