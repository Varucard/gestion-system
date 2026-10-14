<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Request;
use App\Core\Respuesta;
use App\Core\Session;
use App\Enums\Rol;
use App\Exceptions\ValidationException;
use Tests\Support\RespuestaHttp;

/**
 * Tests de controladores: el pedido entra por las mismas rutas, el mismo control de acceso
 * y el mismo controlador que en el navegador, contra la base de prueba.
 *
 * La sesión es el arreglo $_SESSION sin iniciar (en la consola no hay cookies). Para entrar
 * con un usuario, ingresarComo(); el token CSRF se agrega solo en los POST. Las imágenes
 * subidas van a una carpeta temporal que se borra al terminar cada test.
 */
abstract class HttpTestCase extends IntegrationTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $_SESSION = [];
    $this->container->set(Session::class, fn() => new Session());
    App::paraTests(dirname(__DIR__, 2), $this->container, $this->configRoot . '/uploads');
  }

  protected function tearDown(): void
  {
    // Las imágenes que guardó el test (fotos, firmas) van a una carpeta temporal: se borra entera.
    $uploads = $this->configRoot . '/uploads';
    if (is_dir($uploads)) {
      $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($archivos as $archivo) {
        $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
      }
      rmdir($uploads);
    }
    $_SESSION = [];
    unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    parent::tearDown();
  }

  /** Crea un usuario y deja la sesión iniciada con él. Devuelve su id. */
  protected function ingresarComo(Rol $rol = Rol::Empleado, string $usuario = ''): int
  {
    $usuario = $usuario ?: 'prueba' . bin2hex(random_bytes(3));
    $id = $this->make(\App\Services\UsuarioService::class)->crear([
      'nombre' => 'Persona de prueba', 'usuario' => $usuario, 'rol' => $rol->value,
      'clave' => 'clave-de-prueba', 'clave_confirmacion' => 'clave-de-prueba',
    ]);
    $_SESSION['_auth'] = ['id' => $id, 'nombre' => 'Persona de prueba', 'usuario' => $usuario, 'rol' => $rol->value, 'actividad' => time()];

    return $id;
  }

  /**
   * Hace un pedido. Un error de negocio que el controlador no atrapa se responde como lo hace
   * la aplicación (JSON 422 en AJAX, o volver con el mensaje); los demás errores (404, 403…)
   * salen como excepción, para verificarlos con expectException().
   *
   * @param array<string, mixed> $datos  cuerpo del POST (el _token se agrega solo)
   * @param array<string, array<string, mixed>> $archivos  como $_FILES
   */
  protected function pedir(string $metodo, string $uri, array $datos = [], bool $ajax = false, array $archivos = []): RespuestaHttp
  {
    $metodo = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = $metodo;
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
    if ($metodo === 'POST' && !array_key_exists('_token', $datos)) {
      $datos['_token'] = $this->make(Session::class)->csrfToken();
    }
    $request = new Request($metodo, '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/'), $query, $datos, $ajax, $archivos);

    ob_start();
    try {
      try {
        App::instance()->despachar($request);
      } catch (ValidationException $e) {
        throw App::instance()->respuestaDeValidacion($e, $request);
      }
    } catch (Respuesta $r) {
      ob_end_clean();

      return new RespuestaHttp($r->estado, $r->destino(), $r->cuerpo);
    } catch (\Throwable $e) {
      ob_end_clean();
      throw $e;
    }

    return new RespuestaHttp(200, null, (string) ob_get_clean());
  }

  /** @return list<string> mensajes que quedaron para mostrar en la próxima pantalla ("success", "error", "aviso"…) */
  protected function mensajes(string $tipo): array
  {
    return $_SESSION['_flash'][$tipo] ?? [];
  }
}
