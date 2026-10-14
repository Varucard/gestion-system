<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\IntentoRepository;
use PHPUnit\Framework\TestCase;

final class IntentoRedTest extends TestCase
{
  public function testIpv6SeCuentaPorSuRed64(): void
  {
    $this->assertSame('2001:db8:1:2::/64', IntentoRepository::red('2001:db8:1:2:3:4:5:6'));
    $this->assertSame('2001:db8:1:2::/64', IntentoRepository::red('2001:db8:1:2::ffff'));
    $this->assertNotSame(IntentoRepository::red('2001:db8:1:2::1'), IntentoRepository::red('2001:db8:1:3::1'));
  }

  public function testIpv4QuedaIgual(): void
  {
    $this->assertSame('190.51.127.144', IntentoRepository::red('190.51.127.144'));
    $this->assertSame('1.2.3.4', IntentoRepository::red('::ffff:1.2.3.4'));
    $this->assertSame('', IntentoRepository::red(''));
  }
}
