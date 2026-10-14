<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Rol;
use App\Exceptions\ForbiddenException;
use App\Repositories\OrdenRepository;
use App\Services\OrdenService;

/**
 * Lo que hasta ahora se probaba en el navegador: las respuestas JSON de las pantallas
 * (recepción, horarios, buscador) y el cambio de estado por formulario y por AJAX.
 */
final class ControladoresTest extends HttpTestCase
{
  public function testSinSesionVaAlLoginYEnAjaxResponde401(): void
  {
    $r = $this->pedir('GET', '/ordenes');
    $this->assertSame(303, $r->estado);
    $this->assertSame('/login', $r->destino);
    $this->assertSame('/ordenes', $_SESSION['_intended']);

    $r = $this->pedir('GET', '/recepcion/cliente?dni=30111222', ajax: true);
    $this->assertSame(401, $r->estado);
    $this->assertSame('error', $r->json()['status']);
  }

  public function testUnEmpleadoNoEntraALoDeAdministradores(): void
  {
    $this->ingresarComo(Rol::Empleado);

    $this->expectException(ForbiddenException::class);
    $this->pedir('GET', '/configuracion');
  }

  public function testClientePorDniConSusEquiposYSinDatosDeContacto(): void
  {
    $this->ingresarComo();
    $cliente = $this->crearCliente('30111222');
    $equipo = $this->crearEquipo($cliente, 'AB123CD');
    $orden = $this->make(OrdenService::class)->guardar($equipo, [], []);

    $this->assertNull($this->pedir('GET', '/recepcion/cliente?dni=99888777', ajax: true)->json());
    $encontrado = $this->pedir('GET', '/recepcion/cliente?dni=30.111.222', ajax: true)->json();
    $this->assertSame(['id' => $cliente, 'nombre' => 'PÉREZ, JUAN', 'activo' => true], array_diff_key($encontrado, ['equipos' => 0]));
    $this->assertSame($equipo, $encontrado['equipos'][0]['id']);
    $this->assertStringContainsString('S/N AB123CD', $encontrado['equipos'][0]['texto']);
    $this->assertSame([['id' => $orden, 'url' => "/ordenes/{$orden}", 'lista' => false]], $encontrado['equipos'][0]['abiertas']);
    $this->assertStringNotContainsString('1122334455', json_encode($encontrado));
  }

  public function testHorariosLibresDeUnDia(): void
  {
    $this->ingresarComo();
    $this->configurar('turnos', ['horario' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], ['desde' => '09:00', 'hasta' => '11:00']), 'feriados' => []]);
    $manana = date('Y-m-d', strtotime('+1 day'));

    $dia = $this->pedir('GET', "/turnos/horarios?fecha={$manana}", ajax: true)->json();
    $this->assertTrue($dia['abierto']);
    $this->assertSame(['09:00', '10:00'], array_column($dia['horarios'], 'hora'));

    $invalida = $this->pedir('GET', '/turnos/horarios?fecha=31-12-2026', ajax: true)->json();
    $this->assertSame(['abierto' => false, 'motivo' => 'La fecha no es válida.', 'horarios' => []], $invalida);
  }

  public function testSugerenciasDelBuscadorSinTelefonosNiEmails(): void
  {
    $this->ingresarComo();
    $equipo = $this->crearEquipo($this->crearCliente('30111222'), 'AB123CD');
    $orden = $this->make(OrdenService::class)->guardar($equipo, [], []);

    $sugerencias = $this->pedir('GET', '/buscar/sugerencias?q=AB123', ajax: true)->json();
    $this->assertStringContainsString('S/N AB123CD', implode(' | ', array_column($sugerencias, 'detalle')));
    $this->assertStringNotContainsString('1122334455', json_encode($sugerencias));

    // Un dígito solo es un número de orden.
    $porNumero = $this->pedir('GET', "/buscar/sugerencias?q={$orden}", ajax: true)->json();
    $this->assertSame(["Orden #{$orden}"], array_column($porNumero, 'texto'));
    $this->assertSame([], $this->pedir('GET', '/buscar/sugerencias?q=a', ajax: true)->json());
  }

  public function testCambioDeEstadoPorFormularioOfreceDeshacer(): void
  {
    $this->ingresarComo();
    $orden = $this->make(OrdenService::class)->guardar($this->crearEquipo($this->crearCliente()), [], []);

    $r = $this->pedir('POST', "/ordenes/{$orden}/estado", ['estado' => 'en_proceso']);

    $this->assertSame("/ordenes/{$orden}", $r->destino);
    $this->assertSame('en_proceso', $this->make(OrdenRepository::class)->find($orden)['estado']);
    $deshacer = json_decode($this->mensajes('deshacer')[0], true);
    $this->assertSame("ordenes/{$orden}/estado", $deshacer['accion']);
    $this->assertSame('pendiente', $deshacer['campos']['estado']);
  }

  public function testCambioDeEstadoPorAjaxRespondeJsonYLosErroresConUn422(): void
  {
    $this->ingresarComo();
    $orden = $this->make(OrdenService::class)->guardar($this->crearEquipo($this->crearCliente()), [], []);

    $ok = $this->pedir('POST', "/ordenes/{$orden}/estado", ['estado' => 'en_proceso'], ajax: true);
    $this->assertSame(200, $ok->estado);
    $this->assertSame('success', $ok->json()['status']);
    $this->assertSame([], $this->mensajes('deshacer'), 'por AJAX no se ofrece deshacer');

    // Sin ítems no se puede terminar.
    $error = $this->pedir('POST', "/ordenes/{$orden}/estado", ['estado' => 'finalizado'], ajax: true);
    $this->assertSame(422, $error->estado);
    $this->assertStringContainsString('cargá en la orden los servicios', $error->json()['message']);
  }

  public function testSinTokenCsrfNoSeCambiaNada(): void
  {
    $this->ingresarComo();
    $orden = $this->make(OrdenService::class)->guardar($this->crearEquipo($this->crearCliente()), [], []);

    $r = $this->pedir('POST', "/ordenes/{$orden}/estado", ['estado' => 'cancelado', '_token' => 'falso'], ajax: true);

    $this->assertSame(422, $r->estado);
    $this->assertSame('pendiente', $this->make(OrdenRepository::class)->find($orden)['estado']);
  }

  public function testManifiestosDeLaAppInstalable(): void
  {
    $app = $this->pedir('GET', '/app.webmanifest');
    $this->assertSame(200, $app->estado);
    $manifiesto = json_decode($app->html(), true);
    $this->assertSame('Servicio Técnico PC', $manifiesto['name']);
    $this->assertSame('/', $manifiesto['start_url']);

    $this->assertSame('/seguimiento', json_decode($this->pedir('GET', '/portal.webmanifest')->html(), true)['start_url']);
  }
}
