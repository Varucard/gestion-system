<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Rol;
use App\Services\EncuestaService;

/** Encuesta de usabilidad SUS por rondas. */
final class EncuestaTest extends HttpTestCase
{
  /** @return array<string, string> respuestas p1…p10 */
  private static function respuestas(array $valores): array
  {
    return array_combine(array_map(fn($n) => "p{$n}", range(1, 10)), array_map('strval', $valores));
  }

  public function testElPuntajeSusSeCalculaComoEnElEstandar(): void
  {
    $this->assertSame(100.0, EncuestaService::puntaje(array_combine(range(1, 10), [5, 1, 5, 1, 5, 1, 5, 1, 5, 1])));
    $this->assertSame(0.0, EncuestaService::puntaje(array_combine(range(1, 10), [1, 5, 1, 5, 1, 5, 1, 5, 1, 5])));
    $this->assertSame(50.0, EncuestaService::puntaje(array_fill(1, 10, 3)));
    $this->assertSame('Bueno', EncuestaService::lectura(72.5));
    $this->assertSame('Excelente', EncuestaService::lectura(85));
  }

  public function testSeRespondeUnaVezPorRondaYSeComparanLasRondas(): void
  {
    $encuesta = $this->make(EncuestaService::class);
    $usuario = $this->ingresarComo(Rol::Empleado);

    $this->assertFalse($encuesta->pendientePara($usuario), 'sin ronda abierta no se invita');
    $encuesta->abrirRonda('Antes de los cambios');
    $this->assertTrue($encuesta->pendientePara($usuario));
    $this->assertStringContainsString('Nos ayudás a mejorar', $this->pedir('GET', '/')->html());

    $this->pedir('POST', '/encuesta', self::respuestas([4, 2, 4, 2, 4, 2, 4, 2, 4, 2]) + ['comentario' => 'Cuesta encontrar los turnos']);
    $this->assertFalse($encuesta->pendientePara($usuario));
    $this->assertValidationError(fn() => $encuesta->responder($usuario, self::respuestas(array_fill(0, 10, 3))), 'Ya respondiste');

    $encuesta->abrirRonda('Después de los cambios');
    $encuesta->responder($usuario, self::respuestas([5, 1, 5, 1, 5, 1, 5, 1, 5, 2]));

    $resultados = $encuesta->resultados();
    $this->assertSame(['Antes de los cambios', 'Después de los cambios'], array_column($resultados, 'ronda'));
    $this->assertSame([75.0, 97.5], array_column($resultados, 'promedio'));
    $this->assertSame(['Cuesta encontrar los turnos'], $resultados[0]['comentarios']);
  }

  public function testFaltanRespuestasOLaRondaEstaCerrada(): void
  {
    $encuesta = $this->make(EncuestaService::class);
    $usuario = $this->ingresarComo();
    $encuesta->abrirRonda('Ronda de prueba');

    $incompleta = self::respuestas(array_fill(0, 10, 3));
    unset($incompleta['p7']);
    $this->assertValidationError(fn() => $encuesta->responder($usuario, $incompleta), 'las 10 preguntas');
    $this->assertValidationError(fn() => $encuesta->responder($usuario, ['p1' => '9'] + self::respuestas(array_fill(0, 10, 3))), 'las 10 preguntas');

    $encuesta->cerrarRonda();
    $this->assertValidationError(fn() => $encuesta->responder($usuario, self::respuestas(array_fill(0, 10, 3))), 'ya se cerró');
  }

  public function testAhoraNoEscondeLaInvitacionEnLaSesion(): void
  {
    $this->ingresarComo();
    $this->make(EncuestaService::class)->abrirRonda('Ronda de prueba');

    $this->pedir('POST', '/encuesta/despues', ['volver' => '/ordenes']);

    $this->assertStringNotContainsString('Nos ayudás a mejorar', $this->pedir('GET', '/')->html());
  }

  public function testElAdministradorVeLosResultados(): void
  {
    $this->ingresarComo(Rol::Administrador);
    $this->pedir('POST', '/usabilidad/abrir', ['ronda' => 'Primera ronda']);

    $this->assertStringContainsString('Primera ronda', $this->pedir('GET', '/usabilidad')->html());
  }

  public function testAhoraNoSoloVuelveAPantallasDelSistema(): void
  {
    $this->ingresarComo();

    $this->assertSame('/ordenes/12', $this->pedir('POST', '/encuesta/despues', ['volver' => '/ordenes/12'])->destino);
    foreach (['//otro.com', '/\\otro.com', 'https://otro.com', '/a?b=1'] as $volver) {
      $this->assertSame('/', $this->pedir('POST', '/encuesta/despues', ['volver' => $volver])->destino, $volver);
    }
  }
}
