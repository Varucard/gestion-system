<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Corre sobre una base propia: las migraciones hacen DDL y no se pueden revertir. */
final class MigratorTest extends TestCase
{
  private PDO $db;
  private string $dir;

  protected function setUp(): void
  {
    $host = getenv('DB_TEST_HOST');
    if (!$host) {
      $this->markTestSkipped('Tests de integración omitidos: definir DB_TEST_HOST.');
    }

    $this->db = new PDO(
      sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, getenv('DB_TEST_PORT') ?: '3306'),
      getenv('DB_TEST_USERNAME') ?: 'root',
      getenv('DB_TEST_PASSWORD') ?: '',
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
    $this->db->exec('DROP DATABASE IF EXISTS gestion_test_migrador');
    $this->db->exec('CREATE DATABASE gestion_test_migrador');
    $this->db->exec('USE gestion_test_migrador');

    $this->dir = sys_get_temp_dir() . '/migraciones_' . bin2hex(random_bytes(4));
    mkdir($this->dir);
  }

  protected function tearDown(): void
  {
    if (isset($this->db)) {
      $this->db->exec('DROP DATABASE IF EXISTS gestion_test_migrador');
    }
    array_map('unlink', glob(($this->dir ?? '/nada') . '/*') ?: []);
    @rmdir($this->dir ?? '');
  }

  public function testUnaMigracionQueFallaALaMitadSeRetomaDesdeLaSentenciaQueFallo(): void
  {
    file_put_contents("{$this->dir}/0001_prueba.sql", <<<'SQL'
      CREATE TABLE equipos_prueba (id int PRIMARY KEY);
      ALTER TABLE equipos_prueba ADD COLUMN numero_serie varchar(50);
      ALTER TABLE equipos_prueba ADD COLUMN color varchar(20) DEFAULT 'rojo' INVALIDO;
      INSERT INTO equipos_prueba (id, numero_serie) VALUES (1, 'PF2ABC12');
      SQL);
    $migrator = new Migrator($this->db, $this->dir);

    try {
      $migrator->migrate();
      $this->fail('La migración debía fallar');
    } catch (RuntimeException $e) {
      $this->assertStringContainsString('0001_prueba falló en la sentencia 3 de 4', $e->getMessage());
    }

    // Se corrige la sentencia: el reintento no repite las dos primeras (que darían "ya existe").
    file_put_contents("{$this->dir}/0001_prueba.sql", str_replace(' INVALIDO', '', file_get_contents("{$this->dir}/0001_prueba.sql")));
    $log = [];
    $this->assertSame(['0001_prueba'], $migrator->migrate(function (string $m) use (&$log) { $log[] = $m; }));
    $this->assertSame(['Retomando 0001_prueba desde la sentencia 3...'], $log);

    $this->assertSame(['id' => 1, 'numero_serie' => 'PF2ABC12', 'color' => 'rojo'], $this->db->query('SELECT * FROM equipos_prueba')->fetch());
    $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM migraciones_parciales')->fetchColumn());
    $this->assertSame([], $migrator->migrate());
  }
}
