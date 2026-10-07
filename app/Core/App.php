<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PDO;
use Throwable;

/**
 * Punto de arranque de la aplicación: configura el entorno, registra
 * dependencias, despacha la ruta y centraliza el manejo de errores.
 */
final class App
{
  private static ?self $instance = null;

  public readonly Container $container;
  public readonly string $basePath;
  public readonly Logger $logger;

  private function __construct(public readonly string $rootPath)
  {
    Env::load($rootPath . '/.env');

    date_default_timezone_set(Env::get('TZ', 'America/Argentina/Buenos_Aires'));
    ini_set('display_errors', Env::bool('APP_DEBUG') ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $rootPath . '/storage/logs/php-errors.log');
    error_reporting(E_ALL);

    $this->basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    $this->logger = new Logger(
      $rootPath . '/storage/logs',
      Env::get('LOG_LEVEL', Env::bool('APP_DEBUG') ? 'debug' : 'info'),
    );
    $this->logger->agregarContexto([
      'request_id' => bin2hex(random_bytes(6)),
      'canal' => PHP_SAPI === 'cli' ? 'cli' : 'web',
    ]);
    $this->registrarManejadorDeErrores();

    $this->container = new Container();
    $this->container->set(Logger::class, fn() => $this->logger);
    $this->container->set(PDO::class, fn() => Database::connect());
    $this->container->set(View::class, fn() => new View($rootPath . '/views'));
    $this->container->set(Session::class, function () {
      $session = new Session();
      $session->start();

      return $session;
    });
  }

  public static function boot(string $rootPath): self
  {
    return self::$instance ??= new self($rootPath);
  }

  public static function instance(): self
  {
    return self::$instance ?? throw new \LogicException('La aplicación no fue inicializada.');
  }

  public function run(): void
  {
    $request = Request::fromGlobals($this->basePath);
    $this->logger->agregarContexto([
      'ip' => isset($_SERVER['REMOTE_ADDR']) ? Request::ip() : null,
      'ruta' => "{$request->method} {$request->path}",
    ]);
    if (!headers_sent()) {
      header('X-Request-Id: ' . $this->logger->contexto('request_id'));
    }

    try {
      $this->container->get(Session::class);
      $usuario = $this->container->get(Auth::class)->user();
      $this->logger->agregarContexto(['usuario' => $usuario['usuario'] ?? null]);

      $router = new Router($this->container);
      $router->setGuard(fn(string $acceso, Request $r) => $this->guard($acceso, $r));
      (require $this->rootPath . '/config/routes.php')($router);
      $router->dispatch($request);
    } catch (ValidationException $e) {
      $this->handleValidation($e, $request);
    } catch (NotFoundException $e) {
      $this->renderError(404, $e->getMessage(), $request);
    } catch (ForbiddenException $e) {
      $this->renderError(403, $e->getMessage(), $request);
    } catch (Throwable $e) {
      $this->logger->error('Error no controlado: ' . $e->getMessage(), ['exception' => $e]);
      $message = Env::bool('APP_DEBUG') ? $e->getMessage() : 'Ocurrió un error inesperado. Intentá nuevamente.';
      $this->renderError(500, $message, $request);
    }
  }

  /** Warnings y notices de PHP van al log (y se siguen mostrando si APP_DEBUG está activo). */
  private function registrarManejadorDeErrores(): void
  {
    set_error_handler(function (int $tipo, string $mensaje, string $archivo, int $linea): bool {
      if (!(error_reporting() & $tipo)) {
        return false;
      }

      $nivel = match ($tipo) {
        E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'warning',
        E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'notice',
        default => 'error',
      };
      $this->logger->log($nivel, "PHP: {$mensaje}", ['archivo' => "{$archivo}:{$linea}"]);

      return false;
    });
  }

  /** Control de acceso de cada ruta según su nivel (ver Router::ACCESO_*). */
  private function guard(string $acceso, Request $request): void
  {
    if ($acceso === Router::ACCESO_PUBLICO) {
      return;
    }

    $auth = $this->container->get(Auth::class);

    if (!$auth->check()) {
      if ($request->isAjax()) {
        $this->sendJson(['status' => 'error', 'message' => 'La sesión expiró. Volvé a iniciar sesión.'], 401);
        exit;
      }

      if ($request->method === 'GET') {
        $_SESSION['_intended'] = $request->path;
      }
      header('Location: ' . url('login'), true, 303);
      exit;
    }

    if ($acceso === Router::ACCESO_ADMIN && !$auth->esAdministrador()) {
      throw new ForbiddenException('Esta sección es solo para administradores.');
    }
  }

  /** Validaciones no capturadas por el controlador (p. ej. CSRF): volver atrás con el mensaje. */
  private function handleValidation(ValidationException $e, Request $request): void
  {
    if ($request->isAjax()) {
      $this->sendJson(['status' => 'error', 'message' => $e->getMessage()], 422);
      return;
    }

    $session = $this->container->get(Session::class);
    foreach ($e->errors() as $message) {
      $session->flash('error', $message);
    }

    // Solo se vuelve al referer si pertenece a este mismo sitio.
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $refererHost = parse_url($referer, PHP_URL_HOST) . (parse_url($referer, PHP_URL_PORT) ? ':' . parse_url($referer, PHP_URL_PORT) : '');
    $sameSite = $referer !== '' && $refererHost === ($_SERVER['HTTP_HOST'] ?? '');

    header('Location: ' . ($sameSite ? $referer : url('/')), true, 303);
  }

  private function renderError(int $status, string $message, Request $request): void
  {
    if (!headers_sent()) {
      http_response_code($status);
    }

    if ($request->isAjax()) {
      $this->sendJson(['status' => 'error', 'message' => $message], $status);
      return;
    }

    try {
      echo $this->container->get(View::class)->render('errors/error', [
        'title' => match ($status) {
          404 => 'Página no encontrada',
          403 => 'Acceso denegado',
          default => 'Error del sistema',
        },
        'status' => $status,
        'message' => $message,
        'codigo' => $status >= 500 ? $this->logger->contexto('request_id') : null,
      ]);
    } catch (Throwable $e) {
      $this->logger->critical('No se pudo mostrar la página de error', ['exception' => $e]);
      echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
  }

  private function sendJson(array $data, int $status): void
  {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
  }
}
