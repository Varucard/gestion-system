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
   * Secciones con sus páginas, en el orden en que se usan: lo de todos los días primero y
   * la configuración al final. Cada sección tiene una página principal (el clic directo en
   * el botón) y un submenú con el resto.
   *
   * Formato: [ícono de Bootstrap Icons, título, color (clase btn-menu-*, ver tokens.css), ruta principal, [ruta => etiqueta]].
   *
   * @return list<array{0: string, 1: string, 2: string, 3: string, 4: array<string, string>}>
   */
  public static function secciones(bool $admin): array
  {
    return [
      ['clipboard-check', 'Órdenes', 'ordenes', 'ordenes', array_filter([
        'recepcion' => 'Llegó un equipo',
        'ordenes/tablero' => 'Tablero del local',
        'ordenes' => 'Ver órdenes',
        'reportes' => $admin ? 'Reportes' : null,
        'usabilidad' => $admin ? 'Usabilidad (encuesta)' : null,
      ])],
      ['calendar3', 'Turnos', 'turnos', 'turnos', ['turnos/crear' => 'Nuevo turno', 'turnos/semana' => 'Agenda semanal', 'turnos' => 'Ver turnos']],
      ['people', 'Clientes', 'clientes', 'clientes', ['clientes/crear' => 'Nuevo cliente', 'clientes' => 'Ver clientes', 'deudores' => 'Deudores']],
      ['laptop', 'Equipos', 'equipos', 'equipos', ['equipos/crear' => 'Nuevo equipo', 'equipos' => 'Ver equipos']],
      ['box-seam', 'Stock', 'stock', 'repuestos', array_filter([
        'repuestos' => 'Repuestos',
        'proveedores' => 'Proveedores',
        'precios' => $admin ? 'Actualizar precios' : null,
      ])],
      ['gear', 'Configuración', 'config', $admin ? 'configuracion' : 'servicios', array_filter([
        'servicios' => 'Servicios',
        'combos' => 'Combos de servicios',
        'marcas' => 'Marcas',
        'modelos' => 'Modelos',
        'configuracion' => $admin ? 'Sistema' : null,
        'primeros-pasos' => $admin ? 'Primeros pasos' : null,
        'empleados' => $admin ? 'Empleados' : null,
        'usuarios' => $admin ? 'Usuarios' : null,
        'mostrador' => $admin ? 'Modo mostrador (PIN)' : null,
        'auditoria' => $admin ? 'Auditoría' : null,
        'logs' => $admin ? 'Registro del sistema' : null,
      ])],
    ];
  }

  /**
   * ¿La sección actual (primer tramo de la URL) pertenece a esta parte del menú?
   *
   * @param array<string, string> $items
   */
  public static function contiene(array $items, string $principal, string $seccion): bool
  {
    $rutas = [...array_keys($items), $principal];

    return $seccion !== '' && in_array($seccion, array_map(fn($ruta) => explode('/', $ruta)[0], $rutas), true);
  }

  /**
   * Barra inferior del celular: lo que se usa todo el día. El resto va en "Menú".
   * Son cuatro accesos más "Menú", con "Llegó un equipo" exactamente en el medio.
   * Los clientes se encuentran con el buscador o desde "Menú".
   *
   * @return list<array{0: string, 1: string, 2: string}> [ruta, ícono de Bootstrap Icons, etiqueta]
   */
  public static function accesos(): array
  {
    return [
      ['', 'house-door', 'Inicio'],
      ['ordenes', 'clipboard-check', 'Órdenes'],
      ['recepcion', 'laptop-fill', 'Llegó un equipo'],
      ['turnos', 'calendar3', 'Turnos'],
    ];
  }
}
