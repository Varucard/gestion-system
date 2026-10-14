<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AyudaController;
use PHPUnit\Framework\TestCase;

/** El "Volver" de la Ayuda solo lleva a pantallas de este sistema. */
final class AyudaVolverTest extends TestCase
{
  public function testVuelveALaPantallaDeLaQueSeVino(): void
  {
    $this->assertSame(['url' => '/ordenes/12?x=1', 'pantalla' => 'Orden #12'], AyudaController::volver('/ordenes/12?x=1', 'Orden #12', ''));
    $this->assertSame('la pantalla anterior', AyudaController::volver('/clientes', '', '')['pantalla']);
  }

  public function testNoLlevaAOtroSitio(): void
  {
    foreach (['https://otro.com', '//otro.com', '/\\otro.com', "/a\nb", 'javascript:alert(1)', null, ['/x']] as $desde) {
      $this->assertNull(AyudaController::volver($desde, 'X', ''), var_export($desde, true));
    }
  }

  public function testConElSistemaEnUnaSubcarpetaSoloDentroDeElla(): void
  {
    $this->assertNotNull(AyudaController::volver('/gestion/ordenes', 'X', '/gestion'));
    $this->assertNull(AyudaController::volver('/otra-app/x', 'X', '/gestion'));
  }
}
