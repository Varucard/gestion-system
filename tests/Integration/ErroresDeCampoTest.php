<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Support\Validator;

/** Los errores que devuelve el servidor aparecen debajo de su campo, con un resumen arriba. */
final class ErroresDeCampoTest extends HttpTestCase
{
  /** Lo que se guardó para la próxima pantalla pasa a ser "lo de la pantalla anterior" (como al recargar). */
  private function siguientePantalla(): void
  {
    $session = $this->make(Session::class);
    $actual = new \ReflectionProperty($session, 'current');
    $actual->setValue($session, ['_flash' => $_SESSION['_flash'] ?? [], '_old' => $_SESSION['_old'] ?? []]);
    unset($_SESSION['_flash'], $_SESSION['_old']);
  }

  public function testElValidadorRecuerdaDeQueCampoEsCadaError(): void
  {
    try {
      (new Validator())->check(false, 'El DNI está mal.', 'dni')->check(false, 'Otra cosa.')->validate();
      $this->fail('Tenía que fallar');
    } catch (\App\Exceptions\ValidationException $e) {
      $this->assertSame(['El DNI está mal.', 'Otra cosa.'], $e->errors());
      $this->assertSame(['dni' => 'El DNI está mal.'], $e->campos());
    }
  }

  public function testElFormularioVuelveConCadaErrorEnSuCampo(): void
  {
    $this->ingresarComo();

    $r = $this->pedir('POST', '/clientes', ['nombre' => 'Laura', 'apellido' => 'Medina', 'dni' => '12', 'telefono' => '11 2345 6789', 'email' => 'mal']);
    $this->assertSame('/clientes/crear', $r->destino);
    $this->assertSame(['Hay 2 datos para corregir: están marcados en rojo abajo.'], $this->mensajes('error'));

    $this->siguientePantalla();
    $html = $this->pedir('GET', '/clientes/crear')->html();
    $this->assertMatchesRegularExpression('/id="dni"[^>]*class="[^"]*is-invalid|class="[^"]*is-invalid[^"]*" id="dni"/', $html);
    $this->assertStringContainsString('El DNI debe tener entre 6 y 8 dígitos.', $html);
    $this->assertStringContainsString('El email no tiene un formato válido.', $html);
    $this->assertStringNotContainsString('El teléfono no es válido', $html);
  }

  public function testUnErrorSinCampoSigueArriba(): void
  {
    $this->ingresarComo();
    $this->crearCliente('30111222');

    $this->pedir('POST', '/clientes', ['nombre' => 'Otra', 'apellido' => 'Persona', 'dni' => '30111222', 'telefono' => '11 2345 6789']);

    $this->assertNotEmpty($this->mensajes('error'));
    $this->assertStringNotContainsString('marcados en rojo', implode(' ', $this->mensajes('error')));
  }
}
