<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Container;
use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de los tests contra MySQL real.
 *
 * Usa una base descartable (DB_TEST_DATABASE, por defecto "gestion_test") que se
 * recrea con las migraciones una vez por ejecución. Cada test corre dentro de
 * una transacción que se revierte al terminar.
 *
 * Variables: DB_TEST_HOST, DB_TEST_PORT, DB_TEST_USERNAME, DB_TEST_PASSWORD.
 * Si DB_TEST_HOST no está definida, los tests se omiten.
 */
abstract class IntegrationTestCase extends TestCase
{
  private static ?PDO $pdo = null;

  protected PDO $db;
  protected Container $container;
  protected string $configRoot;

  protected function setUp(): void
  {
    $this->db = self::connection();
    $this->db->beginTransaction();

    $this->container = new Container();
    $this->container->set(PDO::class, fn() => $this->db);
    // Configuración en un directorio temporal: los tests no tocan storage/ y
    // no dependen del día en que corren (sin validación de horario por defecto).
    $this->configRoot = sys_get_temp_dir() . '/gestion_it_' . bin2hex(random_bytes(4));
    mkdir($this->configRoot . '/config', 0777, true);
    copy(dirname(__DIR__, 2) . '/config/negocio.php', $this->configRoot . '/config/negocio.php');
    $this->configurar('turnos', ['validar_horario' => false]);
    $this->container->set(
      \App\Services\ConfiguracionService::class,
      fn() => new \App\Services\ConfiguracionService($this->configRoot)
    );
    $this->container->set(\App\Core\Logger::class, fn() => new \App\Core\Logger($this->configRoot . '/logs', 'debug'));
    $this->container->set(\App\Core\View::class, fn() => new \App\Core\View(dirname(__DIR__, 2) . '/views'));
  }

  protected function tearDown(): void
  {
    if ($this->db->inTransaction()) {
      $this->db->rollBack();
    }

    array_map('unlink', glob($this->configRoot . '/logs/*') ?: []);
    @rmdir($this->configRoot . '/logs');
    @unlink($this->configRoot . '/storage/config/negocio.json');
    @unlink($this->configRoot . '/config/negocio.php');
    @rmdir($this->configRoot . '/storage/config');
    @rmdir($this->configRoot . '/storage');
    @rmdir($this->configRoot . '/config');
    @rmdir($this->configRoot);
  }

  /**
   * Sobrescribe valores de una sección de la configuración (sin validar).
   *
   * @param array<string, mixed> $valores
   */
  protected function configurar(string $seccion, array $valores): void
  {
    $archivo = $this->configRoot . '/storage/config/negocio.json';
    @mkdir(dirname($archivo), 0777, true);
    $actual = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];
    $actual[$seccion] = array_replace($actual[$seccion] ?? [], $valores);
    file_put_contents($archivo, json_encode($actual));

    if (isset($this->container)) {
      try {
        $this->make(\App\Services\ConfiguracionService::class)->recargar();
      } catch (\Throwable) {
      }
    }
  }

  /**
   * @template T of object
   * @param class-string<T> $class
   * @return T
   */
  protected function make(string $class): object
  {
    return $this->container->get($class);
  }

  // ---------- Datos de prueba ----------

  /** Verifica que $accion falle con un error de validación que contenga $mensaje. */
  protected function assertValidationError(callable $accion, string $mensaje): void
  {
    try {
      $accion();
    } catch (\App\Exceptions\ValidationException $e) {
      $this->assertStringContainsString($mensaje, implode(' | ', $e->errors()));

      return;
    }
    $this->fail("Se esperaba un error de validación: {$mensaje}");
  }

  protected function crearCliente(string $dni = '30111222'): int
  {
    return $this->make(\App\Services\ClienteService::class)->crear([
      'nombre' => 'Juan', 'apellido' => 'Pérez', 'dni' => $dni, 'telefono' => '1122334455',
    ]);
  }

  /** @return array{marca: int, modelo: int} */
  protected function crearMarcaModelo(string $marca = 'Lenovo', string $modelo = 'IdeaPad 3'): array
  {
    $marcaId = $this->make(\App\Services\MarcaService::class)->guardar($marca);

    return ['marca' => $marcaId, 'modelo' => $this->make(\App\Services\ModeloService::class)->guardar($marcaId, $modelo)];
  }

  protected function crearEquipo(int $clienteId, string $numeroSerie = 'AB123CD'): int
  {
    $mm = $this->crearMarcaModelo('Marca ' . $numeroSerie, 'Modelo');

    return $this->make(\App\Services\EquipoService::class)->crear([
      'cliente_id' => $clienteId, 'tipo' => 'notebook', 'marca_id' => $mm['marca'], 'modelo_id' => $mm['modelo'],
      'numero_serie' => $numeroSerie,
    ]);
  }

  protected function crearServicio(string $nombre, float $precio): int
  {
    return $this->make(\App\Services\ServicioService::class)->guardar(['nombre' => $nombre, 'precio_base' => (string) $precio]);
  }

  protected function crearRepuesto(string $nombre, float $precio): int
  {
    return $this->make(\App\Services\RepuestoService::class)->guardar(['nombre' => $nombre, 'precio' => (string) $precio]);
  }

  private static function connection(): PDO
  {
    if (self::$pdo !== null) {
      return self::$pdo;
    }

    $host = getenv('DB_TEST_HOST');
    if (!$host) {
      self::markTestSkipped('Tests de integración omitidos: definir DB_TEST_HOST.');
    }

    $name = getenv('DB_TEST_DATABASE') ?: 'gestion_test';
    $pdo = new PDO(
      sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, getenv('DB_TEST_PORT') ?: '3306'),
      getenv('DB_TEST_USERNAME') ?: 'root',
      getenv('DB_TEST_PASSWORD') ?: '',
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );

    $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
    $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$name}`");
    (new Migrator($pdo, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

    return self::$pdo = $pdo;
  }
}
