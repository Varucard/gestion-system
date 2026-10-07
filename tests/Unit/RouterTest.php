<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Request;
use App\Core\Router;
use App\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
  public function testDespachaConParametrosNombradosEInyectaDependencias(): void
  {
    $container = new Container();
    $router = new Router($container);
    $router->get('/clientes/{clienteId}/equipos', [FakeController::class, 'show']);

    $router->dispatch(new Request('GET', '/clientes/42/equipos', [], []));

    $controller = $container->get(FakeController::class);
    $this->assertSame(42, $controller->recibido);
    $this->assertInstanceOf(FakeDependency::class, $controller->dependency);
  }

  public function testElMetodoHttpDebeCoincidir(): void
  {
    $router = new Router(new Container());
    $router->post('/clientes/{id}/eliminar', [FakeController::class, 'show']);

    $this->expectException(NotFoundException::class);
    $router->dispatch(new Request('GET', '/clientes/1/eliminar', [], []));
  }

  public function testHeadSeAtiendeConLaRutaGet(): void
  {
    $container = new Container();
    $router = new Router($container);
    $router->get('/clientes/{id}/editar', [FakeController::class, 'show']);

    $router->dispatch(new Request('HEAD', '/clientes/7/editar', [], []));

    $this->assertSame(7, $container->get(FakeController::class)->recibido);
  }

  public function testHeadNoAlcanzaRutasPost(): void
  {
    $router = new Router(new Container());
    $router->post('/clientes/{id}/eliminar', [FakeController::class, 'show']);

    $this->expectException(NotFoundException::class);
    $router->dispatch(new Request('HEAD', '/clientes/1/eliminar', [], []));
  }

  public function testLosParametrosSoloAceptanDigitos(): void
  {
    $router = new Router(new Container());
    $router->get('/clientes/{id}/editar', [FakeController::class, 'show']);

    $this->expectException(NotFoundException::class);
    $router->dispatch(new Request('GET', '/clientes/1 OR 1=1/editar', [], []));
  }
}

final class RouterTextoTest extends TestCase
{
  public function testParametrosDeTexto(): void
  {
    $container = new Container();
    $router = new Router($container);
    $router->get('/config/{seccion:slug}', [FakeTextoController::class, 'show']);
    $router->get('/turno/{token:token}', [FakeTextoController::class, 'token']);

    $router->dispatch(new Request('GET', '/config/turnos', [], []));
    $this->assertSame('turnos', $container->get(FakeTextoController::class)->recibido);

    $token = str_repeat('ab', 32);
    $router->dispatch(new Request('GET', "/turno/{$token}", [], []));
    $this->assertSame($token, $container->get(FakeTextoController::class)->recibido);

    $this->expectException(NotFoundException::class);
    $router->dispatch(new Request('GET', '/turno/corto', [], []));
  }
}

final class FakeTextoController
{
  public ?string $recibido = null;

  public function show(Request $request, string $seccion): void
  {
    $this->recibido = $seccion;
  }

  public function token(Request $request, string $token): void
  {
    $this->recibido = $token;
  }
}

final class FakeDependency
{
}

final class FakeController
{
  public ?int $recibido = null;

  public function __construct(public readonly FakeDependency $dependency)
  {
  }

  public function show(Request $request, int $clienteId = 0, int $id = 0): void
  {
    $this->recibido = $clienteId ?: $id;
  }
}
