<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\TurnoRepository;
use App\Services\TurnoService;

final class TurnosTest extends IntegrationTestCase
{
  private int $cliente;
  private int $equipo;

  protected function setUp(): void
  {
    parent::setUp();
    $this->cliente = $this->crearCliente();
    $this->equipo = $this->crearEquipo($this->cliente);
  }

  private function guardar(string $fecha, string $hora): int
  {
    return $this->make(TurnoService::class)->guardar(['cliente_id' => $this->cliente, 'equipo_id' => $this->equipo, 'fecha' => $fecha, 'hora' => $hora]);
  }

  /** Próxima fecha (desde mañana) con ese día de la semana ISO. */
  private function proximo(int $diaSemana): string
  {
    $fecha = new \DateTimeImmutable('tomorrow');
    while ((int) $fecha->format('N') !== $diaSemana) {
      $fecha = $fecha->modify('+1 day');
    }

    return $fecha->format('Y-m-d');
  }

  public function testRespetaHorarioYFeriados(): void
  {
    $this->configurar('turnos', ['validar_horario' => true, 'feriados' => [$this->proximo(3)]]);

    $this->assertGreaterThan(0, $this->guardar($this->proximo(1), '09:00'));

    foreach ([
      [$this->proximo(1), '19:00', 'fuera del horario de atención (08:00 a 18:00)'],
      [$this->proximo(7), '10:00', 'No atendemos ese día.'],
      [$this->proximo(3), '10:00', 'Esa fecha es feriado.'],
    ] as [$fecha, $hora, $mensaje]) {
      try {
        $this->guardar($fecha, $hora);
        $this->fail("Debía rechazar {$fecha} {$hora}");
      } catch (ValidationException $e) {
        $this->assertStringContainsString($mensaje, $e->getMessage());
      }
    }
  }

  public function testTurnosSimultaneosSegunCupos(): void
  {
    $this->configurar('turnos', ['cupos_por_horario' => 2]);
    $fecha = date('Y-m-d', strtotime('+2 days'));

    $this->guardar($fecha, '10:00');
    $this->guardar($fecha, '10:00');

    $this->expectExceptionMessage('todos los cupos ocupados');
    $this->guardar($fecha, '10:00');
  }

  public function testElClienteConfirmaOCancelaConElLink(): void
  {
    $id = $this->guardar(date('Y-m-d', strtotime('+2 days')), '10:00');
    $token = $this->make(TurnoRepository::class)->token($id);
    $turnos = $this->make(TurnoService::class);

    $turno = $turnos->responderCliente($token, 'confirmar');
    $this->assertSame('confirmado', $turno['estado']);
    $this->assertSame('confirmado', $turno['respuesta_cliente']);

    $turno = $turnos->responderCliente($token, 'cancelar');
    $this->assertSame('cancelado', $turno['estado']);

    $this->expectExceptionMessage('Este turno ya fue cancelado.');
    $turnos->responderCliente($token, 'confirmar');
  }

  public function testElTokenEsEstableYUnico(): void
  {
    $repo = $this->make(TurnoRepository::class);
    $a = $this->guardar(date('Y-m-d', strtotime('+2 days')), '10:00');
    $b = $this->guardar(date('Y-m-d', strtotime('+2 days')), '11:00');

    $this->assertSame($repo->token($a), $repo->token($a));
    $this->assertNotSame($repo->token($a), $repo->token($b));
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $repo->token($a));
  }

  public function testElLinkDelTurnoVenceUnMesDespuesDelTurno(): void
  {
    $repo = $this->make(TurnoRepository::class);
    $id = $this->guardar(date('Y-m-d', strtotime('+2 days')), '10:00');
    $token = $repo->token($id);

    $this->db->exec("UPDATE turnos SET fecha = CURDATE() - INTERVAL 30 DAY WHERE id = {$id}");
    $this->assertNotNull($repo->porToken($token));

    $this->db->exec("UPDATE turnos SET fecha = CURDATE() - INTERVAL 31 DAY WHERE id = {$id}");
    $this->assertNull($repo->porToken($token));
  }

  public function testElCupoSeControlaBajoCandado(): void
  {
    // El candado es reentrante dentro de la misma conexión y se libera al terminar.
    $fecha = date('Y-m-d', strtotime('+2 days'));
    $this->guardar($fecha, '10:00');
    $this->assertValidationError(fn() => $this->guardar($fecha, '10:00'), 'Ya hay un turno agendado');
    $libre = $this->db->query("SELECT IS_FREE_LOCK(CONCAT('gestion:', LEFT(SHA2('turno {$fecha} 10:00', 256), 40)))")->fetchColumn();
    $this->assertSame(1, (int) $libre);
  }
}
