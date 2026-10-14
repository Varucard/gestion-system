<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\NotFoundException;
use App\Services\OrdenService;

/** API del sitio web: un trabajo por ticket + DNI, con clave y bloqueo por intentos. */
final class ApiSeguimientoTest extends HttpTestCase
{
  private const CLAVE = 'clave-de-prueba-de-la-api';

  protected function setUp(): void
  {
    parent::setUp();
    putenv('SEGUIMIENTO_API_KEY=' . self::CLAVE);
    $_SERVER['HTTP_X_API_KEY'] = self::CLAVE;
    $_SERVER['HTTP_X_CLIENTE_IP'] = '203.0.113.' . random_int(1, 250);
  }

  protected function tearDown(): void
  {
    putenv('SEGUIMIENTO_API_KEY');
    unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['HTTP_X_CLIENTE_IP'], $_SERVER['HTTP_AUTHORIZATION']);
    parent::tearDown();
  }

  private function consultar(string $ticket, string $dni = '30111222'): \Tests\Support\RespuestaHttp
  {
    return $this->pedir('POST', '/api/v1/seguimiento', ['ticket' => $ticket, 'dni' => $dni, '_token' => '']);
  }

  private function orden(): int
  {
    $equipo = $this->crearEquipo($this->crearCliente('30111222'), 'PF2ABC12');

    return $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio('Limpieza interna', 15000) => ['cantidad' => '1']], [],
      detalle: ['falla_reportada' => 'Se apaga sola']);
  }

  public function testDevuelveElTrabajoConSuEtapaYSinDatosDeContacto(): void
  {
    $id = $this->orden();

    $r = $this->consultar('PCF-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), '30.111.222');
    $json = $r->json();

    $this->assertSame(200, $r->estado);
    $this->assertSame(str_pad((string) $id, 4, '0', STR_PAD_LEFT), $json['ticket']);
    $this->assertSame('Juan', $json['cliente']);
    $this->assertSame('recibido', $json['etapa']['clave']);
    $this->assertSame([true, false, false, false], array_column($json['etapas'], 'hecha'));
    $this->assertSame('PF2ABC12', $json['equipo']['numero_serie']);
    $this->assertSame(['Limpieza interna'], $json['trabajos']);
    $this->assertSame('Se apaga sola', $json['falla']);
    $this->assertStringNotContainsString('1122334455', $r->html(), 'sin el teléfono del cliente');
  }

  public function testSigueLasEtapasHastaLaEntrega(): void
  {
    $id = $this->orden();
    $ordenes = $this->make(OrdenService::class);
    $ordenes->cambiarEstado($id, 'en_proceso');
    $this->assertSame('en_reparacion', $this->consultar((string) $id)->json()['etapa']['clave']);

    $ordenes->cambiarEstado($id, 'finalizado');
    $this->assertSame('listo', $this->consultar("#{$id}")->json()['etapa']['clave']);

    $this->make(\App\Services\EntregaService::class)->entregar($id, null, null, null);
    $json = $this->consultar((string) $id)->json();
    $this->assertSame('entregado', $json['etapa']['clave']);
    $this->assertSame([true, true, true, true], array_column($json['etapas'], 'hecha'));
  }

  public function testElPresupuestoSinResponderTraeElLinkParaAceptarlo(): void
  {
    $id = $this->orden();
    $this->make(\App\Repositories\OrdenRepository::class)->registrarPresupuestoEnviado($id);

    $presupuesto = $this->consultar((string) $id)->json()['presupuesto'];

    $this->assertSame('esperando_respuesta', $presupuesto['estado']);
    $this->assertStringContainsString('/presupuesto/', $presupuesto['link']);
  }

  public function testUnTicketDeOtroClienteDaElMismoErrorQueUnoQueNoExiste(): void
  {
    $id = $this->orden();
    $this->crearCliente('28999888');

    $ajeno = $this->consultar((string) $id, '28999888');
    $inexistente = $this->consultar('99999');

    $this->assertSame(422, $ajeno->estado);
    $this->assertSame($ajeno->json()['error'], $inexistente->json()['error']);
  }

  public function testDatosIncompletosMarcanElCampo(): void
  {
    $r = $this->consultar('', '12');

    $this->assertSame(422, $r->estado);
    $this->assertArrayHasKey('dni', $r->json()['campos']);
    $this->assertArrayHasKey('ticket', $r->json()['campos']);
  }

  public function testBloqueaPorIpDelClienteTrasVariosIntentosFallidos(): void
  {
    $this->orden();
    for ($i = 0; $i < \App\Services\PortalService::MAX_INTENTOS; $i++) {
      $this->consultar('99999');
    }

    $this->assertSame(429, $this->consultar('99999')->estado);
    // Otra persona (otra IP), con el mismo servidor del sitio en el medio, sigue pudiendo.
    $_SERVER['HTTP_X_CLIENTE_IP'] = '198.51.100.7';
    $this->assertSame(422, $this->consultar('99999', '27111222')->estado);
  }

  public function testSinLaClaveCorrectaNoResponde(): void
  {
    $_SERVER['HTTP_X_API_KEY'] = 'otra';
    $this->assertSame(401, $this->consultar('1')->estado);

    unset($_SERVER['HTTP_X_API_KEY']);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::CLAVE;
    $this->assertSame(422, $this->consultar('99999')->estado, 'también acepta Authorization: Bearer');
  }

  public function testSinClaveConfiguradaLaApiNoExiste(): void
  {
    putenv('SEGUIMIENTO_API_KEY');

    $this->expectException(NotFoundException::class);
    $this->consultar('1');
  }
}
