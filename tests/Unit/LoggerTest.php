<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoggerTest extends TestCase
{
  private string $dir;

  protected function setUp(): void
  {
    $this->dir = sys_get_temp_dir() . '/logs_' . bin2hex(random_bytes(4));
  }

  protected function tearDown(): void
  {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    @rmdir($this->dir);
  }

  public function testEscribeJsonConContextoEInterpolacion(): void
  {
    $logger = new Logger($this->dir, 'info');
    $logger->agregarContexto(['request_id' => 'abc123', 'usuario' => 'tecnico']);
    $logger->info('Orden {orden} finalizada', ['orden' => 12, 'total' => 1500.5]);

    $evento = $logger->leer(date('Y-m-d'))[0];
    $this->assertSame('Orden 12 finalizada', $evento['mensaje']);
    $this->assertSame('info', $evento['nivel']);
    $this->assertSame('abc123', $evento['request_id']);
    $this->assertSame('tecnico', $evento['usuario']);
    $this->assertSame(1500.5, $evento['contexto']['total']);
  }

  public function testRespetaElNivelMinimoYFiltra(): void
  {
    $logger = new Logger($this->dir, 'warning');
    $logger->debug('no');
    $logger->info('tampoco');
    $logger->warning('aviso de stock');
    $logger->error('falló el envío', ['exception' => new RuntimeException('SMTP caído')]);

    $eventos = $logger->leer(date('Y-m-d'));
    $this->assertSame(['falló el envío', 'aviso de stock'], array_column($eventos, 'mensaje'));
    $this->assertSame('SMTP caído', $eventos[0]['excepcion']['mensaje']);

    $this->assertCount(1, $logger->leer(date('Y-m-d'), 'error'));
    $this->assertCount(1, $logger->leer(date('Y-m-d'), 'debug', 'stock'));
  }

  public function testPurgaArchivosViejos(): void
  {
    $logger = new Logger($this->dir, 'info');
    $logger->info('hoy');
    file_put_contents($this->dir . '/app-2020-01-01.log', '{}');

    $this->assertSame([date('Y-m-d'), '2020-01-01'], $logger->fechas());
    $this->assertSame(1, $logger->purgar(30));
    $this->assertSame([date('Y-m-d')], $logger->fechas());
  }

  public function testNoFallaSiNoPuedeEscribir(): void
  {
    $logger = new Logger('/proc/no-se-puede', 'info');
    $logger->error('igual sigue');

    $this->addToAssertionCount(1);
  }
}
