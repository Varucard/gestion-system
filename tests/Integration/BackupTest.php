<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logger;
use App\Services\Auditor;
use App\Services\BackupService;
use App\Services\ConfiguracionService;
use DateTimeImmutable;
use PDO;

final class BackupTest extends IntegrationTestCase
{
  private function servicio(): BackupService
  {
    return new BackupService(
      $this->make(PDO::class),
      $this->make(ConfiguracionService::class),
      $this->make(Logger::class),
      $this->make(Auditor::class),
      $this->configRoot,
    );
  }

  protected function tearDown(): void
  {
    array_map('unlink', glob($this->configRoot . '/storage/backups/*') ?: []);
    @rmdir($this->configRoot . '/storage/backups');
    parent::tearDown();
  }

  public function testGeneraUnVolcadoRestaurableYRota(): void
  {
    $this->crearCliente();
    $backups = $this->servicio();

    $creados = $backups->generar();
    $this->assertMatchesRegularExpression(BackupService::PATRON, $creados[0]);

    $sql = gzdecode((string) file_get_contents($backups->ruta($creados[0])));
    $this->assertStringContainsString('CREATE TABLE `clientes`', $sql);
    $this->assertStringContainsString("INSERT INTO `personas`", $sql);
    $this->assertStringContainsString("'PÉREZ'", $sql);
    $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1;', $sql);

    $this->assertNull($backups->ruta('../../.env'), 'Solo nombres de backup válidos');

    // Rotación: conserva los últimos N.
    foreach (['2026-01-01_100000', '2026-01-02_100000'] as $sello) {
      touch($this->configRoot . "/storage/backups/db_{$sello}.sql.gz");
    }
    $this->assertSame(2, $backups->rotar(1));
    $this->assertCount(1, glob($this->configRoot . '/storage/backups/db_*.gz'));
  }

  public function testConClavePublicaGuardaSoloElBackupCifrado(): void
  {
    $this->crearCliente();
    $claves = \App\Support\CifradoBackup::generarClaves();
    putenv("BACKUP_CLAVE_PUBLICA={$claves['publica']}");

    try {
      $backups = $this->servicio();
      $creados = $backups->generar();
    } finally {
      putenv('BACKUP_CLAVE_PUBLICA');
    }

    $this->assertStringEndsWith('.sql.gz.cif', $creados[0]);
    $this->assertMatchesRegularExpression(BackupService::PATRON, $creados[0]);
    $this->assertSame([], glob($this->configRoot . '/storage/backups/*.gz'), 'No queda ningún backup en claro');
    $this->assertSame($creados[0], $backups->listar()[0]['nombre'] ?? null);

    $vuelta = $this->configRoot . '/storage/backups/vuelta.sql.gz';
    \App\Support\CifradoBackup::descifrar($backups->ruta($creados[0]), $vuelta, $claves['privada']);
    $this->assertStringContainsString("'PÉREZ'", (string) gzdecode((string) file_get_contents($vuelta)));
  }

  public function testCorrespondeUnaVezPorDiaDesdeLaHora(): void
  {
    $this->configurar('backups', ['habilitado' => true, 'hora' => '22:00', 'conservar' => 5]);
    $backups = $this->servicio();
    $hoy = new DateTimeImmutable('today 23:00');

    $this->assertFalse($backups->corresponde(new DateTimeImmutable('today 21:00')));
    $this->assertTrue($backups->corresponde($hoy));
    $backups->generar();
    $this->assertFalse($backups->corresponde($hoy), 'Ya hay backup de hoy');
  }
}
