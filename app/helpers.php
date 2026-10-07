<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;

/**
 * Funciones de ayuda para las vistas.
 */

/** Escapa texto para HTML (contenido y atributos). */
function e(mixed $value): string
{
  return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta dentro de la aplicación, respetando el subdirectorio de instalación. */
function url(string $path = '/'): string
{
  return App::instance()->basePath . '/' . ltrim($path, '/');
}

/**
 * URL de un archivo de public/assets con su fecha de modificación como versión: al
 * cambiar el archivo cambia la URL, y ni el navegador ni la app instalada sirven uno viejo.
 */
function asset(string $path): string
{
  $path = ltrim($path, '/');
  $archivo = App::instance()->rootPath . '/public/assets/' . $path;

  return url('assets/' . $path) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

/**
 * Importe listo para mostrar: "$ 1.234,50" que nunca se parte en dos líneas y con cifras
 * de ancho fijo (clase .importe). Devuelve HTML.
 */
function importe(mixed $amount): string
{
  return '<span class="importe">$&nbsp;' . money($amount) . '</span>';
}

/** Ícono de Bootstrap Icons (https://icons.getbootstrap.com), decorativo: icono('check-lg'). */
function icono(string $nombre): string
{
  return '<i class="bi bi-' . e($nombre) . '" aria-hidden="true"></i>';
}

/**
 * Botón de acción de una fila (Ver, Editar…): ícono + texto. En el celular queda solo el
 * ícono (el texto sigue para lectores de pantalla). Criterio de colores:
 * contorno gris para ver, contorno azul para editar, rojo solo para lo destructivo.
 */
function boton_accion(string $ruta, string $icono, string $texto, string $clase = 'btn-outline-secondary'): string
{
  return '<a href="' . e(url($ruta)) . '" class="btn btn-sm btn-accion ' . e($clase) . '" title="' . e($texto) . '">'
    . icono($icono) . '<span class="btn-texto"> ' . e($texto) . '</span></a>';
}

/** Formato de moneda argentino: 1.234,50 */
function money(mixed $amount): string
{
  return number_format((float) $amount, 2, ',', '.');
}

function format_date(?string $date, string $format = 'd/m/Y'): string
{
  return $date ? date($format, strtotime($date)) : '—';
}

function session(): Session
{
  return App::instance()->container->get(Session::class);
}

/** Valor previo del formulario (si hubo error) o el valor por defecto. */
function old(string $key, mixed $default = ''): mixed
{
  return session()->old($key, $default);
}

function csrf_field(): string
{
  return '<input type="hidden" name="_token" value="' . e(session()->csrfToken()) . '">';
}

function selected(bool $condition): string
{
  return $condition ? 'selected' : '';
}

/** Primer segmento de la ruta actual (p. ej. "clientes"), para resaltar la sección. */
function current_section(): string
{
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  $path = substr($path, strlen(App::instance()->basePath));

  return explode('/', trim($path, '/'))[0] ?? '';
}

function auth(): \App\Core\Auth
{
  return App::instance()->container->get(\App\Core\Auth::class);
}

/** Cantidad sin decimales innecesarios: 2 → "2", 1.5 → "1,5". */
function qty(mixed $value): string
{
  return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
}

/**
 * Teléfono en formato internacional sin "+". Para Argentina (54) un número de
 * 10 dígitos (área + número) se marca como celular: 54 9 + área + número.
 */
function telefono_internacional(string $telefono, string $codigoPais = '54'): string
{
  $digitos = preg_replace('/\D/', '', $telefono);

  if (strlen($digitos) <= 10) {
    return $codigoPais . ($codigoPais === '54' && strlen($digitos) === 10 ? '9' : '') . $digitos;
  }

  return $digitos;
}

/** Link de WhatsApp (wa.me) con un mensaje opcional ya escrito. */
function whatsapp_url(string $telefono, string $mensaje = '', string $codigoPais = '54'): string
{
  return 'https://wa.me/' . telefono_internacional($telefono, $codigoPais)
    . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

/**
 * URL absoluta (para links en emails). Usa APP_URL; si no está definida y hay
 * una petición web en curso, la arma con el host actual.
 */
function absolute_url(string $path = '/'): string
{
  $base = \App\Core\Env::get('APP_URL');

  if ($base === null) {
    $esquema = \App\Core\Request::esHttps() ? 'https' : 'http';
    $base = isset($_SERVER['HTTP_HOST']) ? $esquema . '://' . $_SERVER['HTTP_HOST'] . App::instance()->basePath : 'http://localhost';
  }

  return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/** URL de la ficha de una entidad auditada, o null si no tiene pantalla propia. */
function url_entidad(string $entidad, ?int $id): ?string
{
  if ($id === null) {
    return null;
  }

  $rutas = [
    'orden' => "ordenes/{$id}", 'cliente' => "clientes/{$id}", 'equipo' => "equipos/{$id}",
    'repuesto' => "repuestos/{$id}/stock", 'turno' => "turnos/{$id}/editar", 'usuario' => "usuarios/{$id}/editar",
    'empleado' => "empleados/{$id}/editar", 'servicio' => "servicios/{$id}/editar", 'proveedor' => "proveedores/{$id}/editar",
    'combo' => "combos/{$id}/editar",
  ];

  return isset($rutas[$entidad]) ? url($rutas[$entidad]) : null;
}

/**
 * Descripción de un equipo para mostrar: "Notebook Lenovo IdeaPad 3 · S/N PF2ABC12".
 * Toma de la fila las claves tipo, marca, modelo y numero_serie (las que estén).
 *
 * @param array<string, mixed> $fila
 */
function equipo_texto(array $fila, bool $conSerie = true): string
{
  $texto = trim(implode(' ', array_filter([
    \App\Enums\TipoEquipo::etiqueta($fila['tipo'] ?? null),
    (string) ($fila['marca'] ?? ''),
    (string) ($fila['modelo'] ?? ''),
  ])));

  return $conSerie && !empty($fila['numero_serie']) ? "{$texto} · S/N {$fila['numero_serie']}" : $texto;
}
