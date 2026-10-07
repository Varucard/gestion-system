<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestIpTest extends TestCase
{
  public function testSinProxiesDeConfianzaIgnoraLaCabecera(): void
  {
    $server = ['REMOTE_ADDR' => '200.1.1.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'];

    $this->assertSame('200.1.1.1', Request::ip($server, ''));
  }

  public function testDetrasDeUnProxyDeConfianzaUsaLaIpReal(): void
  {
    $server = ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 181.10.20.30, 10.0.0.2'];

    // El cliente puede inventar las primeras IPs de la lista; vale la última que no es de un proxy propio.
    $this->assertSame('181.10.20.30', Request::ip($server, '172.16.0.0/12, 10.0.0.2'));
  }

  public function testUnaPeticionDirectaNoPuedeFalsificarLaIp(): void
  {
    $server = ['REMOTE_ADDR' => '181.10.20.30', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'];

    $this->assertSame('181.10.20.30', Request::ip($server, '172.16.0.0/12'));
  }

  public function testRangosIpv6YCabeceraInvalida(): void
  {
    $this->assertSame('2800:810::1', Request::ip(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '2800:810::1'], '::1'));
    $this->assertSame('172.18.0.5', Request::ip(['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => 'basura'], '172.16.0.0/12'));
  }

  public function testHttpsDetrasDelProxy(): void
  {
    $this->assertTrue(Request::esHttps(['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https'], '172.16.0.0/12'));
    $this->assertFalse(Request::esHttps(['REMOTE_ADDR' => '181.10.20.30', 'HTTP_X_FORWARDED_PROTO' => 'https'], '172.16.0.0/12'));
    $this->assertTrue(Request::esHttps(['HTTPS' => 'on'], ''));
  }
}
