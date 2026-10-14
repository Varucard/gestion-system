<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Manual;
use PHPUnit\Framework\TestCase;

/** La Ayuda arma sus páginas desde docs/manual/manual.html: que no se desarme al editarlo. */
final class ManualTest extends TestCase
{
  private const MANUAL = __DIR__ . '/../../docs/manual/manual.html';

  /** @return list<array{id: string, titulo: string, html: string}> */
  private function capitulos(): array
  {
    return (new Manual(self::MANUAL))->capitulos(fn(string $nombre) => "/ayuda/capturas/{$nombre}");
  }

  public function testCadaBotonDeAyudaApuntaAUnaParteQueExiste(): void
  {
    $html = implode('', array_map(fn(array $c) => "<section id=\"{$c['id']}\">" . $c['html'], $this->capitulos()));

    foreach (Manual::destinos() as $id) {
      $this->assertStringContainsString("id=\"{$id}\"", $html, "El botón de Ayuda apunta a #{$id}, que no está en manual.html (¿cambió un subtítulo?).");
    }
  }

  public function testElDestinoEsElDeLaRutaMasLargaQueFigura(): void
  {
    $this->assertSame('turnos-agenda-semanal', Manual::destinoDe('/turnos/semana'));
    $this->assertSame('ordenes-cargar-trabajos-y-repuestos', Manual::destinoDe('/ordenes/12/editar'));
    $this->assertSame('ordenes-la-ficha-de-la-orden-que-sigue', Manual::destinoDe('/ordenes/12'));
    $this->assertSame('pantalla-principal-el-inicio', Manual::destinoDe('/'));
    // Sin una propia, la de la sección.
    $this->assertSame('stock-repuestos', Manual::destinoDe('/repuestos/3/editar'));
    $this->assertNull(Manual::destinoDe('/no-existe'));
  }

  public function testLasSeccionesConAyudaSonRutasDelSistema(): void
  {
    $rutas = file_get_contents(__DIR__ . '/../../config/routes.php');

    // Las de los catálogos (servicios, marcas…) se arman en un foreach de routes.php: acá van las escritas a mano.
    foreach (['clientes/crear', 'ordenes/{id}/editar', 'recepcion', 'turnos/semana', 'deudores', 'repuestos/{id}/stock', 'proveedores', 'precios', 'logs'] as $ruta) {
      $this->assertNotNull(Manual::destinoDe('/' . str_replace('{id}', '1', $ruta)));
      $this->assertStringContainsString("'/{$ruta}'", $rutas, "No hay una ruta /{$ruta}.");
    }
  }

  public function testSinPortadaYConElCapituloDeComoLeer(): void
  {
    $capitulos = $this->capitulos();

    $this->assertSame('como-leer', $capitulos[0]['id']);
    $this->assertSame('Cómo leer este manual', $capitulos[0]['titulo']);
    $this->assertStringNotContainsString('<ol>', $capitulos[0]['html'], 'El índice impreso no va: la pantalla tiene el suyo.');
    $this->assertStringNotContainsString('portada', implode('', array_column($capitulos, 'html')));
  }

  public function testLasCapturasSeSirvenDesdeLaAyudaYExisten(): void
  {
    $html = implode('', array_column($this->capitulos(), 'html'));

    preg_match_all('#<img src="/ayuda/capturas/([a-z0-9-]+)"[^>]*>#', $html, $capturas);
    $this->assertNotEmpty($capturas[1]);
    $this->assertStringNotContainsString('src="capturas/', $html);
    foreach ($capturas[1] as $i => $nombre) {
      $this->assertFileExists(__DIR__ . "/../../docs/manual/capturas/{$nombre}.png");
      $this->assertStringContainsString('loading="lazy"', $capturas[0][$i]);
    }
  }

  public function testLosSubtitulosSePuedenEnlazar(): void
  {
    $this->assertStringContainsString('id="primeros-pasos-cambiar-tu-contrasena"', $this->capitulos()[1]['html']);
  }

  public function testLaVersionParaImprimirLlevaLaVersionYElLogo(): void
  {
    $html = (new Manual(self::MANUAL))->paraImprimir(fn(string $n) => "/c/{$n}", '/logo.png', '9.8.7');

    $this->assertStringStartsWith('<!DOCTYPE html>', $html);
    $this->assertStringContainsString('<span class="js-version">9.8.7</span>', $html);
    $this->assertStringContainsString('<img src="/logo.png"', $html);
    $this->assertStringContainsString('src="/c/01-login"', $html);
    $this->assertStringContainsString('print()', $html);
  }
}
