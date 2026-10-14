<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * El manual de usuario dentro del sistema (Ayuda). La fuente es una sola,
 * docs/manual/manual.html, la misma del PDF: acá se parte en capítulos (uno por <section>
 * con id) y se le cambian las rutas de las capturas para que las sirva AyudaController.
 *
 * Los estilos del manual no se usan en pantalla (son para A4): la vista los toma de
 * paginas.css, con los colores de la app y el modo oscuro.
 */
final class Manual
{
  /**
   * Parte del manual que explica cada pantalla: ruta (sin la barra inicial; "#" = un
   * número) => id de un capítulo o de un subtítulo de manual.html. Gana el tramo más largo:
   * "ordenes/#/editar" va a su subtítulo y, si una ruta no figura (p. ej. "repuestos/#/editar"),
   * se le van sacando tramos hasta dar con una que sí ("repuestos"). Lo usa el botón de Ayuda.
   */
  private const DESTINOS = [
    '' => 'pantalla-principal-el-inicio',
    'buscar' => 'pantalla-principal-el-buscador',
    'perfil' => 'primeros-pasos-cambiar-tu-contrasena',
    'recepcion' => 'llego-un-equipo',
    'clientes' => 'clientes',
    'clientes/crear' => 'clientes-nuevo-cliente',
    'clientes/#' => 'clientes-la-ficha-del-cliente',
    'clientes/#/editar' => 'clientes-nuevo-cliente',
    'clientes/#/equipos' => 'equipos-nuevo-equipo',
    'equipos' => 'equipos',
    'equipos/crear' => 'equipos-nuevo-equipo',
    'equipos/#' => 'equipos-la-ficha-del-equipo',
    'equipos/#/editar' => 'equipos-nuevo-equipo',
    'turnos' => 'turnos-el-listado-de-turnos',
    'turnos/crear' => 'turnos-nuevo-turno',
    'turnos/#/editar' => 'turnos-nuevo-turno',
    'turnos/semana' => 'turnos-agenda-semanal',
    'ordenes' => 'ordenes-el-listado-de-ordenes',
    'ordenes/crear' => 'ordenes',
    'ordenes/#' => 'ordenes-la-ficha-de-la-orden-que-sigue',
    'ordenes/#/editar' => 'ordenes-cargar-trabajos-y-repuestos',
    'ordenes/tablero' => 'ordenes-el-tablero-del-local',
    'ordenes/#/entregar' => 'ordenes-entregar-el-equipo',
    'ordenes/#/firmar' => 'llego-un-equipo-fotos-y-firma-al-recibir',
    'ordenes/#/presupuesto' => 'presupuestos',
    'ordenes/#/entrega' => 'presupuestos',
    'pagos' => 'pagos-cobrar',
    'deudores' => 'pagos-deudores',
    'servicios' => 'servicios-servicios',
    'combos' => 'servicios-combos-de-servicios',
    'marcas' => 'servicios-marcas-y-modelos',
    'modelos' => 'servicios-marcas-y-modelos',
    'repuestos' => 'stock-repuestos',
    'repuestos/#/stock' => 'stock-movimientos-de-stock',
    'proveedores' => 'stock-proveedores',
    'precios' => 'precios',
    'reportes' => 'reportes',
    'configuracion' => 'configuracion',
    'configuracion/backups' => 'auditoria-backups',
    'usuarios' => 'usuarios-usuarios',
    'empleados' => 'usuarios-empleados',
    'auditoria' => 'auditoria-auditoria',
    'logs' => 'auditoria-registro-del-sistema',
    'mostrador' => 'usuarios-modo-mostrador-pin',
    'primeros-pasos' => 'primeros-pasos-la-primera-vez',
    'usabilidad' => 'reportes-encuesta-de-usabilidad',
    'encuesta' => 'reportes-encuesta-de-usabilidad',
  ];

  /** @var list<array{id: string, titulo: string, html: string}>|null */
  private ?array $capitulos = null;

  public function __construct(private readonly string $archivo)
  {
  }

  /**
   * Parte del manual (id) que explica una pantalla, o null si no hay una propia.
   *
   * @param string $ruta  la ruta de la pantalla, sin la base: "/ordenes/12/editar"
   */
  public static function destinoDe(string $ruta): ?string
  {
    $tramos = array_map(fn(string $t) => ctype_digit($t) ? '#' : $t, explode('/', trim($ruta, '/')));

    for ($n = count($tramos); $n > 0; $n--) {
      $clave = implode('/', array_slice($tramos, 0, $n));
      if (isset(self::DESTINOS[$clave])) {
        return self::DESTINOS[$clave];
      }
    }

    return null;
  }

  /** @return list<string> ids a los que apunta algún botón de Ayuda */
  public static function destinos(): array
  {
    return array_values(array_unique(self::DESTINOS));
  }

  /**
   * Los capítulos, en orden, listos para mostrar. Sin la portada; el índice impreso se
   * reemplaza por el de la pantalla y de esa hoja queda solo "Cómo leer este manual".
   *
   * @param callable(string): string $urlCaptura nombre de la captura (sin .png) => URL
   * @return list<array{id: string, titulo: string, html: string}>
   */
  public function capitulos(callable $urlCaptura): array
  {
    if ($this->capitulos !== null) {
      return $this->capitulos;
    }

    $documento = $this->documento();
    $this->rutasDeCapturas($documento, $urlCaptura, perezosas: true);
    $xpath = new DOMXPath($documento);
    $capitulos = [];

    /** @var DOMElement $seccion */
    foreach ($xpath->query('/html/body/section[@id]') as $seccion) {
      $id = $seccion->getAttribute('id');

      if ($id === 'como-leer') {
        // Fuera el "Contenido" impreso: el título del capítulo pasa a ser el subtítulo.
        foreach (['h2', 'ol'] as $etiqueta) {
          self::primero($xpath, $etiqueta, $seccion)?->remove();
        }
        $subtitulo = self::primero($xpath, 'h3', $seccion);
        $h2 = $documento->createElement('h2', trim((string) $subtitulo?->textContent) ?: 'Cómo leer este manual');
        $subtitulo?->remove();
        $seccion->prepend($h2);
      }

      $titulo = trim((string) self::primero($xpath, 'h2', $seccion)?->textContent);
      // Cada subtítulo también se puede enlazar: #ordenes-cobrar-una-orden.
      /** @var DOMElement $h3 */
      foreach ($xpath->query('.//h3', $seccion) as $h3) {
        $h3->setAttribute('id', $id . '-' . self::slug($h3->textContent));
      }

      $html = '';
      foreach ($seccion->childNodes as $hijo) {
        $html .= $documento->saveHTML($hijo);
      }

      $capitulos[] = ['id' => $id, 'titulo' => $titulo, 'html' => $html];
    }

    return $this->capitulos = $capitulos;
  }

  /**
   * El manual completo, con sus estilos de A4, para imprimirlo o guardarlo como PDF desde el
   * navegador: es lo mismo que arma generar-pdf.js, sin instalar nada.
   *
   * @param callable(string): string $urlCaptura
   */
  public function paraImprimir(callable $urlCaptura, string $urlLogo, string $version): string
  {
    $documento = $this->documento();
    $xpath = new DOMXPath($documento);
    $this->rutasDeCapturas($documento, $urlCaptura, perezosas: false);
    self::primero($xpath, "section[@class='portada']//img")?->setAttribute('src', $urlLogo);
    self::ponerVersion($xpath, $version);

    // El diálogo de imprimir se abre solo, cuando ya cargaron todas las capturas.
    $script = $documento->createElement('script', "addEventListener('load', () => print());");
    self::primero($xpath, 'body')?->append($script);

    return "<!DOCTYPE html>\n" . $documento->saveHTML($documento->documentElement);
  }

  /** Versión y mes de la portada (los marcadores .js-version y .js-fecha de manual.html). */
  private static function ponerVersion(DOMXPath $xpath, string $version): void
  {
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    self::primero($xpath, "span[@class='js-version']")?->replaceChildren($version);
    self::primero($xpath, "span[@class='js-fecha']")?->replaceChildren($meses[(int) date('n') - 1] . ' ' . date('Y'));
  }

  /** @param callable(string): string $urlCaptura */
  private function rutasDeCapturas(DOMDocument $documento, callable $urlCaptura, bool $perezosas): void
  {
    /** @var DOMElement $img */
    foreach ((new DOMXPath($documento))->query("//img[starts-with(@src, 'capturas/')]") as $img) {
      $archivo = dirname($this->archivo) . '/' . $img->getAttribute('src');
      $img->setAttribute('src', $urlCaptura(basename($archivo, '.png')));
      if ($perezosas) {
        // 60 capturas pesan 13 MB: en pantalla se bajan a medida que se llega a cada una. Con
        // el tamaño declarado, la página no salta cuando una termina de cargar (ni el link a
        // un capítulo cae en otro lado).
        $img->setAttribute('loading', 'lazy');
        if ($medidas = @getimagesize($archivo)) {
          $img->setAttribute('width', (string) $medidas[0]);
          $img->setAttribute('height', (string) $medidas[1]);
        }
      }
    }
  }

  private function documento(): DOMDocument
  {
    // libxml lee HTML como Latin-1 si no se le avisa que es UTF-8. Los avisos de etiquetas
    // de HTML5 (section, figure…) que no conoce se descartan: las respeta igual.
    $documento = new DOMDocument();
    $documento->loadHTML('<?xml encoding="UTF-8">' . file_get_contents($this->archivo), LIBXML_NOERROR | LIBXML_NOWARNING);

    return $documento;
  }

  /** Primer elemento que cumple la expresión (relativa a $contexto, o a todo el documento). */
  private static function primero(DOMXPath $xpath, string $expresion, ?DOMElement $contexto = null): ?DOMElement
  {
    $nodo = $xpath->query(($contexto ? './/' : '//') . $expresion, $contexto)->item(0);

    return $nodo instanceof DOMElement ? $nodo : null;
  }

  /** "Cómo escribir importes…" => "como-escribir-importes…" (ids de los subtítulos). */
  private static function slug(string $texto): string
  {
    // A mano y no con iconv: su transliteración depende del locale y en "C" deja "?".
    $ascii = strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
  }
}
