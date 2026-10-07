<?php

declare(strict_types=1);

/**
 * Gestión de usuarios desde la consola (útil para recuperar el acceso).
 *
 *   php bin/usuario.php crear <usuario> "<Nombre y apellido>"   crea un administrador
 *   php bin/usuario.php clave <usuario>                        cambia la contraseña y lo reactiva
 *
 * En Docker: docker compose exec public php bin/usuario.php clave admin
 */

use App\Core\App;
use App\Enums\Rol;
use App\Exceptions\ValidationException;
use App\Repositories\UsuarioRepository;
use App\Services\UsuarioService;

require dirname(__DIR__) . '/vendor/autoload.php';

[$comando, $usuario, $nombre] = array_pad(array_slice($argv, 1), 3, null);

if (!in_array($comando, ['crear', 'clave'], true) || !$usuario || ($comando === 'crear' && !$nombre)) {
  fwrite(STDERR, "Uso:\n  php bin/usuario.php crear <usuario> \"<Nombre>\"\n  php bin/usuario.php clave <usuario>\n");
  exit(1);
}

function leerClave(string $prompt): string
{
  echo $prompt;
  $tty = stream_isatty(STDIN);
  if ($tty) {
    shell_exec('stty -echo');
  }
  $clave = rtrim((string) fgets(STDIN), "\r\n");
  if ($tty) {
    shell_exec('stty echo');
    echo PHP_EOL;
  }

  return $clave;
}

// Mismo contenedor que la aplicación (base, logger, configuración, auditoría).
$container = App::boot(dirname(__DIR__))->container;
$service = $container->get(UsuarioService::class);
$repo = $container->get(UsuarioRepository::class);

$clave = leerClave('Contraseña: ');
$confirmacion = leerClave('Repetir contraseña: ');

try {
  if ($comando === 'crear') {
    $service->crear(
      ['nombre' => $nombre, 'usuario' => $usuario, 'clave' => $clave, 'clave_confirmacion' => $confirmacion],
      Rol::Administrador
    );
    echo "Administrador '{$usuario}' creado.\n";
  } else {
    $fila = $repo->findParaLogin(mb_strtolower($usuario)) ?? throw new ValidationException(["No existe el usuario '{$usuario}'."]);
    $actual = $repo->find((int) $fila['id']);
    $service->actualizar((int) $fila['id'], [
      'nombre' => $actual['nombre'], 'usuario' => $actual['usuario'], 'rol' => $actual['rol'], 'activo' => 1,
      'clave' => $clave, 'clave_confirmacion' => $confirmacion,
    ], 0);
    $service->desbloquear($actual['usuario']);
    echo "Contraseña de '{$usuario}' actualizada.\n";
  }
} catch (ValidationException $e) {
  fwrite(STDERR, implode(PHP_EOL, $e->errors()) . PHP_EOL);
  exit(1);
}
