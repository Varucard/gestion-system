<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Enums\Rol;
use App\Services\MostradorService;
use App\Services\UsuarioService;

/** Modo mostrador: entrar con PIN, solo desde un equipo habilitado. */
final class MostradorTest extends HttpTestCase
{
  protected function tearDown(): void
  {
    unset($_COOKIE[MostradorService::COOKIE]);
    parent::tearDown();
  }

  /** Un empleado con PIN 4826 y este "navegador" habilitado como mostrador. @return array{0: int, 1: string} id y token */
  private function preparar(): array
  {
    $usuarios = $this->make(UsuarioService::class);
    $id = $usuarios->crear(['nombre' => 'Hernán', 'usuario' => 'hernan', 'rol' => 'empleado', 'clave' => 'clave-segura', 'clave_confirmacion' => 'clave-segura']);
    $usuarios->definirPin($id, 'clave-segura', '4826', '4826');
    $token = $this->make(MostradorService::class)->habilitar('PC del mostrador', null);
    $_COOKIE[MostradorService::COOKIE] = $token;

    return [$id, $token];
  }

  public function testEnElMostradorSeEntraTocandoElNombreYConElPin(): void
  {
    [$id] = $this->preparar();

    $this->assertStringContainsString('Hernán', $this->pedir('GET', '/login')->html());
    $r = $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '4826']);

    $this->assertSame('/', $r->destino);
    $this->assertSame($id, $this->make(Auth::class)->id());
    $this->assertTrue($this->make(Auth::class)->enMostrador());
  }

  public function testSinElEquipoHabilitadoElPinNoSirve(): void
  {
    [$id] = $this->preparar();
    unset($_COOKIE[MostradorService::COOKIE]);

    $this->assertStringContainsString('Contraseña', $this->pedir('GET', '/login')->html());
    $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '4826']);

    $this->assertNull($this->make(Auth::class)->id());
    $this->assertStringContainsString('no está habilitado', $this->mensajes('error')[0]);
  }

  public function testCincoPinEquivocadosBloquean(): void
  {
    [$id] = $this->preparar();
    $mostrador = $this->make(MostradorService::class)->deCookie($_COOKIE[MostradorService::COOKIE]);
    $servicio = $this->make(MostradorService::class);

    for ($i = 0; $i < MostradorService::MAX_INTENTOS; $i++) {
      $this->assertValidationError(fn() => $servicio->entrar($mostrador, $id, '1111', '10.0.0.1'), match ($i) {
        0 => 'Te quedan 4 intentos', 3 => 'Te queda 1 intento', 4 => 'queda bloqueado', default => 'no es correcto',
      });
    }
    // Ni con el PIN correcto, hasta que pase el bloqueo.
    $this->assertValidationError(fn() => $servicio->entrar($mostrador, $id, '4826', '10.0.0.1'), 'Demasiados PIN equivocados');
  }

  public function testLaSesionDelMostradorSeCierraALosPocosMinutos(): void
  {
    [$id] = $this->preparar();
    $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '4826']);
    $_SESSION['_auth']['actividad'] = time() - MostradorService::INACTIVIDAD - 1;

    $this->assertNull((new Auth($this->make(\App\Core\Session::class), $this->make(\App\Repositories\UsuarioRepository::class)))->user());
  }

  public function testNoSeAceptanPinObviosYSePideLaContrasena(): void
  {
    foreach (['1234', '0000', '1212', '123', '12345', 'abcd'] as $pin) {
      $this->assertFalse(MostradorService::pinValido($pin), $pin);
    }
    $this->assertTrue(MostradorService::pinValido('4826'));

    $id = $this->make(UsuarioService::class)->crear(['nombre' => 'Ana', 'usuario' => 'ana', 'rol' => 'empleado', 'clave' => 'clave-segura', 'clave_confirmacion' => 'clave-segura']);
    $this->assertValidationError(fn() => $this->make(UsuarioService::class)->definirPin($id, 'otra-clave', '4826', '4826'), 'contraseña actual');
  }

  public function testElAdministradorHabilitaYQuitaEquipos(): void
  {
    $this->ingresarComo(Rol::Administrador);

    $this->pedir('POST', '/mostrador', ['nombre' => 'Tablet del negocio']);
    $mostrador = $this->make(MostradorService::class)->listar()[0];
    $this->assertSame('Tablet del negocio', $mostrador['nombre']);
    $this->assertNotNull($this->make(MostradorService::class)->deCookie($_COOKIE[MostradorService::COOKIE] ?? null));

    $this->pedir('POST', "/mostrador/{$mostrador['id']}/quitar");
    $this->assertSame([], $this->make(MostradorService::class)->listar());
    $this->assertArrayNotHasKey(MostradorService::COOKIE, $_COOKIE);
  }

  public function testConPinUnAdministradorNoEntraALoDeAdministradores(): void
  {
    $usuarios = $this->make(UsuarioService::class);
    $id = $usuarios->crear(['nombre' => 'Ana', 'usuario' => 'ana', 'rol' => 'administrador', 'clave' => 'clave-segura', 'clave_confirmacion' => 'clave-segura']);
    $usuarios->definirPin($id, 'clave-segura', '4826', '4826');
    $_COOKIE[MostradorService::COOKIE] = $this->make(MostradorService::class)->habilitar('PC', null);
    $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '4826']);

    $this->assertFalse($this->make(Auth::class)->esAdministrador());
    $this->expectException(\App\Exceptions\ForbiddenException::class);
    $this->expectExceptionMessage('Cambiar de usuario');
    $this->pedir('GET', '/configuracion/backups');
  }

  public function testConUnPinEquivocadoQuedaElegidaLaMismaPersonaYSeVaSiempreAlInicio(): void
  {
    [$id] = $this->preparar();
    $_SESSION['_intended'] = '/clientes/1';

    $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '1111']);
    $this->assertSame((string) $id, $_SESSION['_old']['usuario_id']);

    $r = $this->pedir('POST', '/login/pin', ['usuario_id' => $id, 'pin' => '4826']);
    $this->assertSame('/', $r->destino, 'no cae en la pantalla que miraba el anterior');
  }

  public function testEnElMostradorUnaContrasenaEquivocadaVuelveAlFormularioDeContrasena(): void
  {
    $this->preparar();

    $r = $this->pedir('POST', '/login', ['usuario' => 'hernan', 'clave' => 'equivocada']);

    $this->assertSame('/login?con_clave=1', $r->destino, 'no a «¿Quién sos?»');
  }
}
