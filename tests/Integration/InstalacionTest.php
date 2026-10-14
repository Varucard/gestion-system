<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Alta del primer administrador: sin INSTALACION_TOKEN no se puede desde la web (se crea por
 * consola) y con él hay que saberlo. Si no, el primero que llegara a la dirección pública
 * antes que el dueño se quedaría con el sistema.
 */
final class InstalacionTest extends HttpTestCase
{
  private const DATOS = ['nombre' => 'Dueño', 'usuario' => 'duenio', 'clave' => 'clave-segura', 'clave_confirmacion' => 'clave-segura'];

  protected function tearDown(): void
  {
    putenv('INSTALACION_TOKEN');
    parent::tearDown();
  }

  private function usuarios(): int
  {
    return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
  }

  public function testSinTokenSoloPorConsola(): void
  {
    $this->assertSame(0, $this->usuarios());
    $this->assertStringContainsString('bin/usuario.php crear', $this->pedir('GET', '/instalacion')->html());

    $r = $this->pedir('POST', '/instalacion', self::DATOS);
    $this->assertSame('/instalacion', $r->destino);
    $this->assertSame(0, $this->usuarios());
  }

  public function testConTokenHayQueSaberlo(): void
  {
    putenv('INSTALACION_TOKEN=codigo-de-prueba');
    $this->assertStringContainsString('token_instalacion', $this->pedir('GET', '/instalacion')->html());

    $this->pedir('POST', '/instalacion', ['token_instalacion' => 'otro'] + self::DATOS);
    $this->assertSame(0, $this->usuarios());

    $r = $this->pedir('POST', '/instalacion', ['token_instalacion' => 'codigo-de-prueba'] + self::DATOS);
    $this->assertSame('/primeros-pasos', $r->destino);
    $this->assertSame(1, $this->usuarios());

    // Ya hay usuarios: la pantalla deja de existir (404).
    $this->expectException(\App\Exceptions\NotFoundException::class);
    $this->pedir('GET', '/instalacion');
  }
}
