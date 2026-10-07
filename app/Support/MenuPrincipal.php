<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contenido del menú principal. Lo usan los dos formatos del menú: los botones de
 * la computadora (partials/navbar) y la barra inferior + menú lateral del celular
 * (partials/menu_movil).
 */
final class MenuPrincipal
{
  /**
   * Secciones con sus páginas: [ícono de Bootstrap Icons, título, color del botón, [ruta => etiqueta]].
   *
   * @return list<array{0: string, 1: string, 2: string, 3: array<string, string>}>
   */
  public static function secciones(bool $admin): array
  {
    return [
      ['gear', 'Configuración', 'primary', array_filter([
        'servicios' => 'Servicios',
        'combos' => 'Combos de servicios',
        'marcas' => 'Marcas',
        'modelos' => 'Modelos',
        'configuracion' => $admin ? 'Sistema' : null,
        'empleados' => $admin ? 'Empleados' : null,
        'usuarios' => $admin ? 'Usuarios' : null,
        'auditoria' => $admin ? 'Auditoría' : null,
        'logs' => $admin ? 'Registro del sistema' : null,
      ])],
      ['people', 'Clientes', 'success', ['clientes/crear' => 'Registrar cliente', 'clientes' => 'Ver clientes', 'deudores' => 'Deudores']],
      ['laptop', 'Equipos', 'info', ['equipos/crear' => 'Registrar equipo', 'equipos' => 'Ver equipos']],
      ['clipboard-check', 'Órdenes', 'warning', array_filter([
        'ordenes/crear' => 'Registrar orden',
        'ordenes' => 'Ver órdenes',
        'reportes' => $admin ? 'Reportes' : null,
      ])],
      ['box-seam', 'Stock', 'dark', array_filter([
        'repuestos' => 'Repuestos',
        'proveedores' => 'Proveedores',
        'precios' => $admin ? 'Actualizar precios' : null,
      ])],
      ['calendar3', 'Turnos', 'secondary', ['turnos/crear' => 'Registrar turno', 'turnos/semana' => 'Agenda semanal', 'turnos' => 'Ver turnos']],
    ];
  }

  /**
   * Barra inferior del celular: lo que se usa todo el día. El resto va en "Menú".
   *
   * @return list<array{0: string, 1: string, 2: string}> [ruta, ícono de Bootstrap Icons, etiqueta]
   */
  public static function accesos(): array
  {
    return [
      ['', 'house-door', 'Inicio'],
      ['ordenes', 'clipboard-check', 'Órdenes'],
      ['turnos', 'calendar3', 'Turnos'],
      ['clientes', 'people', 'Clientes'],
    ];
  }
}
