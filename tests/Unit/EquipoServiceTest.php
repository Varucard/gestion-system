<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EquipoService;
use PHPUnit\Framework\TestCase;

final class EquipoServiceTest extends TestCase
{
  public function testNormalizaElNumeroDeSerieComoSeEscriba(): void
  {
    $this->assertSame('PF2ABC12', EquipoService::normalizarSerie(' pf2 abc12 '));
    $this->assertSame('SN-123/A', EquipoService::normalizarSerie('sn-123/a'));
  }

  public function testNumeroDeSerieValido(): void
  {
    $this->assertTrue(EquipoService::serieValida('PF2ABC12'));
    $this->assertFalse(EquipoService::serieValida('AB'));
    $this->assertFalse(EquipoService::serieValida('ABC#123'));
  }
}
