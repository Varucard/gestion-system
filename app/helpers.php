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

/**
 * Versión del sistema (archivo VERSION de la raíz). No se edita a mano: la sube el workflow
 * .github/workflows/version.yml al mergear cada PR a main.
 */
function version_app(): string
{
  static $version = null;

  return $version ??= trim((string) @file_get_contents(App::instance()->rootPath . '/VERSION')) ?: 'sin versión';
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

/** Error del servidor para un campo del formulario que volvió con errores (ver Controller::backWithErrors). */
function error_campo(string $nombre): ?string
{
  $campos = json_decode((string) (session()->messages()['campos'][0] ?? '[]'), true) ?: [];

  return $campos[rtrim($nombre, '[]')] ?? null;
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

/** Ruta actual sin la carpeta de instalación ni la query: "ordenes/12". */
function current_section_path(): string
{
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

  return trim(substr($path, strlen(App::instance()->basePath)), '/');
}

/**
 * Ayuda de la pantalla actual: el manual abierto en la parte que la explica, con la pantalla
 * de la que se viene (desde + su título) para el botón "Volver" de la Ayuda.
 */
function url_ayuda(string $pantalla = ''): string
{
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $ruta = substr(parse_url($uri, PHP_URL_PATH) ?: '/', strlen(App::instance()->basePath));

  if (current_section() === 'ayuda') {
    return url('ayuda');
  }

  $destino = \App\Support\Manual::destinoDe($ruta);

  return url('ayuda') . '?' . http_build_query(['desde' => $uri, 'pantalla' => $pantalla])
    . ($destino !== null ? '#' . $destino : '');
}

function auth(): \App\Core\Auth
{
  return App::instance()->container->get(\App\Core\Auth::class);
}

/**
 * Descripción de un ítem de la orden (fila de OrdenRepository::items) para mostrar: el
 * servicio, o el repuesto (del catálogo o descrito a mano), aclarando si lo trae el cliente.
 */
function item_orden(array $item): string
{
  if ($item['servicio_id'] !== null) {
    return (string) $item['servicio_nombre'];
  }

  $nombre = 'Repuesto: ' . ($item['repuesto_nombre'] ?? $item['descripcion']);

  return $item['provisto_cliente'] ? "{$nombre} (provisto por el cliente)" : $nombre;
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

/**
 * Link de WhatsApp con un mensaje opcional ya escrito. Según cómo se abra (ver whatsapp_modo()):
 *   wame  https://wa.me/… — el link universal: en el celular abre la app; en la PC muestra
 *         primero una página que pregunta si seguir en la app o en WhatsApp Web.
 *   web   https://web.whatsapp.com/send — WhatsApp Web directo (con la sesión del navegador).
 *   app   whatsapp://send — la app de escritorio instalada, sin pasar por el navegador.
 */
function whatsapp_url(string $telefono, string $mensaje = '', string $codigoPais = '54', string $modo = 'wame'): string
{
  $numero = telefono_internacional($telefono, $codigoPais);
  $texto = $mensaje !== '' ? rawurlencode($mensaje) : '';

  return match ($modo) {
    'web' => "https://web.whatsapp.com/send?phone={$numero}" . ($texto !== '' ? "&text={$texto}" : ''),
    'app' => "whatsapp://send?phone={$numero}" . ($texto !== '' ? "&text={$texto}" : ''),
    default => "https://wa.me/{$numero}" . ($texto !== '' ? "?text={$texto}" : ''),
  };
}

/** ¿El pedido viene de un celular o una tablet? (Ahí wa.me abre la app directo.) */
function es_celular(?string $userAgent = null): bool
{
  return (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * Cómo se abre WhatsApp en este equipo: en el celular, siempre wa.me (abre la app); en la PC,
 * lo elegido en Configuración › Avisos (por defecto, WhatsApp Web directo).
 *
 * @param array<string, mixed> $notificaciones la sección "notificaciones" de la configuración
 */
function whatsapp_modo(array $notificaciones, ?string $userAgent = null): string
{
  return es_celular($userAgent) ? 'wame' : (string) ($notificaciones['whatsapp_abrir'] ?? 'web');
}

/**
 * Dónde se abre el link. La app de escritorio, sin abrir pestañas (la página queda donde
 * estaba). WhatsApp Web y wa.me, en una pestaña nueva: web.whatsapp.com manda
 * Cross-Origin-Opener-Policy: same-origin, así que el navegador no deja reutilizar una pestaña
 * con nombre (target="whatsapp") y cada clic abre otra.
 */
function whatsapp_target(string $modo): string
{
  return $modo === 'app' ? '_self' : '_blank';
}

/** Target de los botones de WhatsApp en las vistas (con la configuración del sistema). */
function whatsapp_target_actual(): string
{
  return whatsapp_target(whatsapp_modo(App::instance()->container->get(\App\Services\ConfiguracionService::class)->seccion('notificaciones')));
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
