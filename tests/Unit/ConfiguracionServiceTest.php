<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\ConfiguracionService;
use PHPUnit\Framework\TestCase;

final class ConfiguracionServiceTest extends TestCase
{
  private string $root;

  protected function setUp(): void
  {
    $this->root = sys_get_temp_dir() . '/gestion_' . bin2hex(random_bytes(4));
    mkdir($this->root . '/config', 0777, true);
    copy(__DIR__ . '/../../config/negocio.php', $this->root . '/config/negocio.php');
  }

  protected function tearDown(): void
  {
    array_map('unlink', glob($this->root . '/{config,storage/config}/*', GLOB_BRACE) ?: []);
    @rmdir($this->root . '/storage/config');
    @rmdir($this->root . '/storage');
    @rmdir($this->root . '/config');
    @rmdir($this->root);
  }

  private function datosValidos(): array
  {
    return [
      'nombre' => 'Negocio <Prueba>', 'cuit' => '20-12345678-9', 'direccion' => 'Calle 1',
      'telefono' => '1234', 'whatsapp' => '+5491112345678', 'email' => 'a@b.com',
    ];
  }

  private function trabajoValido(): array
  {
    return [
      'validez' => '15', 'garantia' => '30', 'tiempo_estimado' => '2',
      'forma_pago' => "Contado\r\n\r\n Transferencia ", 'observaciones' => '', 'mensaje_legal' => "'; system('id'); //",
    ];
  }

  public function testSinArchivoGuardadoUsaLosValoresPorDefecto(): void
  {
    $config = (new ConfiguracionService($this->root))->obtener();

    $this->assertSame('Servicio Técnico PC', $config['negocio']['nombre']);
    $this->assertSame(10, $config['trabajo']['validez']);
  }

  public function testGuardaComoJsonYLoRelee(): void
  {
    (new ConfiguracionService($this->root))->guardar('negocio', $this->datosValidos());
    (new ConfiguracionService($this->root))->guardar('trabajo', $this->trabajoValido());

    $this->assertFileExists($this->root . '/storage/config/negocio.json');
    $config = (new ConfiguracionService($this->root))->obtener();

    $this->assertSame('Negocio <Prueba>', $config['negocio']['nombre']);
    $this->assertSame(['Contado', 'Transferencia'], $config['trabajo']['forma_pago']);
    $this->assertSame([], $config['trabajo']['observaciones']);
    $this->assertSame(15, $config['trabajo']['validez']);
    // El texto se guarda como dato: nunca se genera ni ejecuta código PHP.
    $this->assertSame("'; system('id'); //", $config['trabajo']['mensaje_legal']);
  }

  public function testRechazaDatosInvalidos(): void
  {
    $this->expectException(ValidationException::class);

    (new ConfiguracionService($this->root))->guardar('negocio', ['cuit' => '123'] + $this->datosValidos());
  }

  public function testGuardarUnaSeccionNoPisaLasDemas(): void
  {
    $service = new ConfiguracionService($this->root);
    $service->guardar('negocio', $this->datosValidos());
    $service->guardar('stock', ['margen_sugerido' => '40']);

    $config = (new ConfiguracionService($this->root))->obtener();
    $this->assertSame('Negocio <Prueba>', $config['negocio']['nombre']);
    $this->assertFalse($config['stock']['permitir_negativo']);
    $this->assertTrue($config['portal']['habilitado'], 'Las secciones no guardadas mantienen sus valores por defecto');
  }

  public function testHorarioYFeriados(): void
  {
    $service = new ConfiguracionService($this->root);
    $service->guardar('turnos', [
      'horario' => [1 => ['abierto' => '1', 'desde' => '09:00', 'hasta' => '17:00']],
      'cupos_por_horario' => '2', 'recordatorio_hora' => '11:00', 'feriados' => "2026-12-25\n2026-01-01\n2026-12-25",
      'validar_horario' => '1',
    ]);

    $turnos = (new ConfiguracionService($this->root))->seccion('turnos');
    $this->assertSame(['desde' => '09:00', 'hasta' => '17:00'], $turnos['horario'][1]);
    $this->assertNull($turnos['horario'][2]);
    $this->assertSame(['2026-01-01', '2026-12-25'], $turnos['feriados']);
    $this->assertFalse($turnos['enviar_confirmacion']);

    $this->expectExceptionMessage('la apertura debe ser anterior al cierre');
    $service->guardar('turnos', ['horario' => [1 => ['abierto' => '1', 'desde' => '18:00', 'hasta' => '09:00']], 'cupos_por_horario' => '1', 'recordatorio_hora' => '10:00']);
  }

  public function testLosMensajesSoloAceptanVariablesConocidas(): void
  {
    $mensajes = (require $this->root . '/config/negocio.php')['mensajes'];
    $service = new ConfiguracionService($this->root);

    try {
      $service->guardar('mensajes', ['email_recordatorio' => 'Hola {nombre_cliente}'] + $mensajes);
      $this->fail('Debía rechazar la variable desconocida');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('{nombre_cliente}', $e->getMessage());
    }

    // Variables que existen pero no corresponden a ese aviso: quedarían sin reemplazar.
    foreach (['email_presupuesto' => '{link_turno}', 'email_mantenimiento' => '{fecha}', 'whatsapp_recordatorio' => '{total}'] as $campo => $variable) {
      try {
        $service->guardar('mensajes', [$campo => "Hola {cliente} {$variable}"] + $mensajes);
        $this->fail("Debía rechazar {$variable} en {$campo}");
      } catch (ValidationException $e) {
        $this->assertStringContainsString("\"{$campo}\" usa variables que no corresponden a ese aviso: {$variable}", $e->getMessage());
      }
    }

    $this->expectExceptionMessage('debe incluir {link_turno}');
    $service->guardar('mensajes', ['email_confirmacion' => 'Hola {cliente}'] + $mensajes);
  }
}
