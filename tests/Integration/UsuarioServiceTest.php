<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Session;
use App\Enums\Rol;
use App\Exceptions\ValidationException;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;

final class UsuarioServiceTest extends IntegrationTestCase
{
  private UsuarioService $usuarios;

  protected function setUp(): void
  {
    parent::setUp();
    $this->usuarios = $this->make(UsuarioService::class);
  }

  private function crear(string $usuario, Rol $rol = Rol::Empleado): int
  {
    return $this->usuarios->crear([
      'nombre' => 'Prueba', 'usuario' => $usuario, 'rol' => $rol->value,
      'clave' => 'clave-segura', 'clave_confirmacion' => 'clave-segura',
    ]);
  }

  public function testAutenticaConCredencialesValidasSinExponerElHash(): void
  {
    $this->crear('Tecnico');

    $usuario = $this->usuarios->autenticar(' tecnico ', 'clave-segura', '10.0.0.1');

    $this->assertSame('tecnico', $usuario['usuario']);
    $this->assertArrayNotHasKey('password_hash', $usuario);
    $this->assertSame(60, strlen($this->db->query("SELECT password_hash FROM usuarios")->fetchColumn()));
  }

  public function testRechazaClaveIncorrectaYUsuarioInactivo(): void
  {
    $id = $this->crear('tecnico');

    try {
      $this->usuarios->autenticar('tecnico', 'otra', '10.0.0.1');
      $this->fail('Debía rechazar la clave');
    } catch (ValidationException $e) {
      $this->assertSame(['Usuario o contraseña incorrectos.'], $e->errors());
    }

    $this->crear('admin', Rol::Administrador);
    $this->usuarios->actualizar($id, ['nombre' => 'Prueba', 'usuario' => 'tecnico', 'rol' => 'empleado'], 999);

    $this->expectExceptionMessage('Usuario o contraseña incorrectos.');
    $this->usuarios->autenticar('tecnico', 'clave-segura', '10.0.0.2');
  }

  public function testBloqueaTrasDemasiadosIntentos(): void
  {
    $this->crear('tecnico');

    for ($i = 0; $i < UsuarioService::MAX_INTENTOS; $i++) {
      try {
        $this->usuarios->autenticar('tecnico', 'mal', '10.0.0.3');
      } catch (ValidationException) {
      }
    }

    // Incluso con la clave correcta queda bloqueado.
    $this->expectExceptionMessage('Demasiados intentos fallidos');
    $this->usuarios->autenticar('tecnico', 'clave-segura', '10.0.0.3');
  }

  public function testSiempreQuedaUnAdministradorActivo(): void
  {
    $admin = $this->crear('admin', Rol::Administrador);
    $otro = $this->crear('jefe', Rol::Administrador);

    // No puede degradarse a sí mismo.
    try {
      $this->usuarios->actualizar($admin, ['nombre' => 'Admin', 'usuario' => 'admin', 'rol' => 'empleado', 'activo' => 1], $admin);
      $this->fail('No debía permitir quitarse el rol');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('No podés quitarte el rol', $e->getMessage());
    }

    // Otro admin sí puede degradarlo, mientras quede uno.
    $this->usuarios->actualizar($admin, ['nombre' => 'Admin', 'usuario' => 'admin', 'rol' => 'empleado', 'activo' => 1], $otro);

    $this->expectExceptionMessage('al menos un administrador activo');
    $this->usuarios->actualizar($otro, ['nombre' => 'Jefe', 'usuario' => 'jefe', 'rol' => 'empleado', 'activo' => 1], 999);
  }

  public function testValidaClaveYUsuarioDuplicado(): void
  {
    $this->crear('tecnico');

    try {
      $this->usuarios->crear(['nombre' => 'Xavier', 'usuario' => 'otro', 'rol' => 'empleado', 'clave' => 'corta', 'clave_confirmacion' => 'corta']);
      $this->fail('Debía rechazar la clave corta');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('al menos', $e->getMessage());
    }

    $this->expectExceptionMessage('Ese nombre de usuario ya existe.');
    $this->crear('TECNICO');
  }

  public function testUnaClaveInvalidaNoDejaLaEdicionAMedias(): void
  {
    $id = $this->crear('tecnico');

    $this->assertValidationError(
      fn() => $this->usuarios->actualizar($id, ['nombre' => 'Otro Nombre', 'usuario' => 'tecnico', 'rol' => 'administrador', 'activo' => 1, 'clave' => 'corta', 'clave_confirmacion' => 'corta'], 999),
      'al menos',
    );

    $fila = $this->db->query("SELECT nombre, rol FROM usuarios WHERE id = {$id}")->fetch();
    $this->assertSame(['nombre' => 'Prueba', 'rol' => 'empleado'], $fila);
  }

  public function testLaSesionSeRevalidaContraLaBase(): void
  {
    $id = $this->crear('tecnico');
    // Cada request arma su propio Auth sobre la misma sesión.
    $request = fn() => new Auth($this->make(Session::class), $this->make(UsuarioRepository::class));
    $_SESSION = ['_auth' => ['id' => $id, 'nombre' => 'Prueba', 'usuario' => 'tecnico', 'rol' => 'empleado', 'actividad' => time()]];
    $this->assertFalse($request()->esAdministrador());

    // Un cambio de rol rige en el request siguiente, sin volver a iniciar sesión.
    $this->db->exec("UPDATE usuarios SET rol = 'administrador' WHERE id = {$id}");
    $this->assertTrue($request()->esAdministrador());

    // Al desactivarlo, la sesión se corta.
    $this->db->exec("UPDATE usuarios SET activo = 0 WHERE id = {$id}");
    $this->assertNull($request()->user());
    $this->assertArrayNotHasKey('_auth', $_SESSION);
    $_SESSION = [];
  }

  public function testIntentosDesdeOtraIpNoBloqueanAlUsuario(): void
  {
    $this->crear('tecnico');
    $fallar = function (string $usuario, string $ip) {
      try {
        $this->usuarios->autenticar($usuario, 'mal', $ip);
      } catch (ValidationException) {
      }
    };

    // Alguien desde otra IP agota los intentos de "tecnico": el dueño, desde su IP, entra igual.
    for ($i = 0; $i < UsuarioService::MAX_INTENTOS; $i++) {
      $fallar('tecnico', '6.6.6.6');
    }
    $this->assertSame('tecnico', $this->usuarios->autenticar('tecnico', 'clave-segura', '10.0.0.9')['usuario']);

    // Una IP que prueba muchos usuarios distintos queda bloqueada para todos.
    for ($i = 0; $i < UsuarioService::MAX_INTENTOS_IP; $i++) {
      $fallar("usuario{$i}", '7.7.7.7');
    }
    $this->assertValidationError(fn() => $this->usuarios->autenticar('tecnico', 'clave-segura', '7.7.7.7'), 'Demasiados intentos');
  }
}
