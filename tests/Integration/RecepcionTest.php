<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ClienteRepository;
use App\Repositories\EquipoRepository;
use App\Repositories\ModeloRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Services\DocumentoService;
use App\Services\EquipoService;
use App\Services\MarcaService;
use App\Services\OrdenService;
use App\Services\RecepcionService;
use App\Services\TurnoService;

/** "Llegó un equipo" y las órdenes que se abren sin ítems. */
final class RecepcionTest extends IntegrationTestCase
{
  private function recibir(array $input): int
  {
    return $this->make(RecepcionService::class)->recibir($input);
  }

  /** Datos de un equipo nuevo, para completar con el cliente. */
  private function equipoNuevo(array $mm, array $extra = []): array
  {
    return ['tipo' => 'notebook', 'marca_id' => $mm['marca'], 'modelo_id' => $mm['modelo']] + $extra;
  }

  public function testEquipoConocidoAbreLaOrdenSinItems(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());

    $id = $this->recibir([
      'dni' => '30.111.222', 'equipo_id' => (string) $equipo, 'falla_reportada' => 'No enciende',
      'accesorios' => 'Cargador original', 'estado_ingreso' => 'Rayón en la tapa',
    ]);

    $orden = $this->make(OrdenRepository::class)->find($id);
    $this->assertSame($equipo, (int) $orden['equipo_id']);
    $this->assertSame('pendiente', $orden['estado']);
    $this->assertSame('No enciende', $orden['falla_reportada']);
    $this->assertSame('Cargador original', $orden['accesorios']);
    $this->assertSame('Rayón en la tapa', $orden['estado_ingreso']);
    $this->assertEquals(0, $orden['total']);
    $this->assertSame([], $this->make(OrdenRepository::class)->items($id));
  }

  public function testClienteNuevoCargaClienteEquipoYModeloDeUnaVez(): void
  {
    $marca = $this->make(MarcaService::class)->guardar('Lenovo');

    $id = $this->recibir([
      'dni' => '28.111.222', 'nombre' => 'Ana', 'apellido' => 'Gómez', 'telefono' => '011 15 2345-6789',
      'tipo' => 'notebook', 'marca_id' => $marca, 'modelo_id' => EquipoService::MODELO_NUEVO . 'IdeaPad 3', 'numero_serie' => ' pf2 abc12 ',
    ]);

    $cliente = $this->make(ClienteRepository::class)->porDni('28111222');
    $this->assertNotNull($cliente);
    $this->assertSame('1123456789', $cliente['telefono'], 'El teléfono se guarda normalizado');
    $equipo = $this->make(EquipoRepository::class)->porSerie('PF2ABC12');
    $this->assertSame('IdeaPad 3', $equipo['modelo'], 'El modelo escrito se agregó al catálogo de la marca');
    $this->assertSame((int) $cliente['id'], (int) $equipo['cliente_id']);
    $this->assertSame((int) $equipo['id'], (int) $this->make(OrdenRepository::class)->find($id)['equipo_id']);
  }

  public function testEquipoNuevoDeUnClienteQueYaExiste(): void
  {
    $cliente = $this->crearCliente('30111222');
    $mm = $this->crearMarcaModelo();

    $this->recibir(['dni' => '30111222', 'nombre' => '', 'apellido' => '', 'telefono' => ''] + $this->equipoNuevo($mm, ['numero_serie' => 'AD789FG']));

    $this->assertSame($cliente, (int) $this->make(EquipoRepository::class)->porSerie('AD789FG')['cliente_id']);
  }

  public function testElModeloEscritoNoSeDuplicaSiYaExiste(): void
  {
    $mm = $this->crearMarcaModelo('HP', 'Pavilion 15');

    $this->recibir([
      'dni' => '30111333', 'nombre' => 'Luis', 'apellido' => 'Sosa', 'telefono' => '1144556677', 'tipo' => 'notebook',
      'marca_id' => $mm['marca'], 'modelo_id' => EquipoService::MODELO_NUEVO . ' pavilion 15 ', 'numero_serie' => 'AE111AA',
    ]);

    $this->assertCount(1, $this->make(ModeloRepository::class)->porMarca($mm['marca']));
    $this->assertSame($mm['modelo'], (int) $this->make(EquipoRepository::class)->porSerie('AE111AA')['modelo_id']);
  }

  public function testSiAlgoNoValidaNoQuedaNadaAMedioCargar(): void
  {
    $marca = $this->make(MarcaService::class)->guardar('Lenovo');

    $this->assertValidationError(fn() => $this->recibir([
      'dni' => '31222333', 'nombre' => 'Eva', 'apellido' => 'Paz', 'telefono' => '1144556677',
      'tipo' => 'lavarropas', 'marca_id' => $marca, 'modelo_id' => EquipoService::MODELO_NUEVO . 'ThinkPad',
    ]), 'tipo de equipo');

    $this->assertNull($this->make(ClienteRepository::class)->porDni('31222333'), 'El cliente no quedó creado');
    $this->assertSame([], $this->make(ModeloRepository::class)->porMarca($marca), 'El modelo tampoco');
  }

  public function testDniInvalidoEquipoDeOtroClienteOEquipoDadoDeBaja(): void
  {
    $this->assertValidationError(fn() => $this->recibir(['dni' => '12']), 'Escribí el DNI');

    $equipo = $this->crearEquipo($this->crearCliente('30111222'));
    $this->crearCliente('30999888');
    $this->assertValidationError(fn() => $this->recibir(['dni' => '30999888', 'equipo_id' => (string) $equipo]), 'no es de este cliente');

    $this->make(EquipoService::class)->alternarEstado($equipo);
    $this->assertValidationError(fn() => $this->recibir(['dni' => '30111222', 'equipo_id' => (string) $equipo]), 'está dado de baja');
  }

  public function testAvisaSiElEquipoYaTieneUnaOrdenAbierta(): void
  {
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $recepcion = $this->make(RecepcionService::class);
    $this->assertSame([], $recepcion->equiposDe($cliente)[0]['abiertas']);

    $conocido = ['dni' => '30111222', 'equipo_id' => (string) $equipo];
    $id = $this->recibir($conocido);
    $this->assertSame([$id], array_map('intval', array_column($recepcion->equiposDe($cliente)[0]['abiertas'], 'id')));

    // Ya está en el local: otra orden solo si se confirma a propósito.
    $this->assertValidationError(fn() => $this->recibir($conocido), "ya tiene la orden #{$id} abierta");
    $this->assertValidationError(fn() => $this->recibir($conocido + ['confirmar_otra' => '1']), 'ya tiene la orden'); // un "sí" genérico no alcanza
    $otra = $this->recibir($conocido + ['confirmar_otra' => (string) $id]);
    $this->assertNotSame($id, $otra);
    // Ahora la abierta más nueva es $otra: confirmar con la vieja ya no vale (se abrió otra mientras tanto).
    $this->assertValidationError(fn() => $this->recibir($conocido + ['confirmar_otra' => (string) $id]), "ya tiene la orden #{$otra} abierta");
    $this->assertNull($recepcion->buscarDni('99999999'));
    $this->assertNull($recepcion->buscarDni('cualquier cosa'));
  }

  public function testDesdeUnTurnoElTurnoQuedaRealizado(): void
  {
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $turno = $this->make(TurnoService::class)->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => date('Y-m-d'), 'hora' => '23:59']);

    $this->recibir(['dni' => '30111222', 'equipo_id' => (string) $equipo, 'turno_id' => (string) $turno]);

    $this->assertSame('realizado', $this->make(TurnoRepository::class)->find($turno)['estado']);
  }

  public function testUnTurnoYaUsadoOCanceladoNoAbreOtraOrden(): void
  {
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $conocido = ['dni' => '30111222', 'equipo_id' => (string) $equipo];
    $turnos = $this->make(TurnoService::class);
    $turno = $turnos->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => date('Y-m-d'), 'hora' => '23:58']);
    $id = $this->recibir($conocido + ['turno_id' => (string) $turno]);

    $this->assertValidationError(fn() => $this->recibir($conocido + ['turno_id' => (string) $turno, 'confirmar_otra' => (string) $id]), 'ya no está pendiente');

    $cancelado = $turnos->guardar(['cliente_id' => $cliente, 'equipo_id' => $equipo, 'fecha' => date('Y-m-d'), 'hora' => '23:57']);
    $turnos->cambiarEstado($cancelado, 'cancelado');
    $this->assertValidationError(fn() => $this->recibir($conocido + ['turno_id' => (string) $cancelado, 'confirmar_otra' => (string) $id]), 'ya no está pendiente');
  }

  public function testUnClienteDadoDeBajaSeReactivaAlTraerUnEquipoNuevo(): void
  {
    $cliente = $this->crearCliente('30111444');
    $this->make(\App\Services\ClienteService::class)->alternarEstado($cliente);

    $this->recibir(['dni' => '30111444'] + $this->equipoNuevo($this->crearMarcaModelo()));

    $this->assertSame('activo', $this->make(ClienteRepository::class)->find($cliente)['estado']);
  }

  public function testMarcaYModeloNuevosEscribiendolos(): void
  {
    $this->recibir([
      'dni' => '30111555', 'nombre' => 'Sol', 'apellido' => 'Ríos', 'telefono' => '3515551234', 'tipo' => 'pc', 'numero_serie' => 'AH200BB',
      'marca_id' => EquipoService::MODELO_NUEVO . 'Genérica', 'modelo_id' => EquipoService::MODELO_NUEVO . 'PC armada',
    ]);

    $equipo = $this->make(EquipoRepository::class)->porSerie('AH200BB');
    $this->assertSame('Genérica', $equipo['marca']);
    $this->assertSame('PC armada', $equipo['modelo']);
  }

  public function testMarcaOModeloEscritosInvalidosNoSeCrean(): void
  {
    $base = ['dni' => '30111666', 'nombre' => 'Ana', 'apellido' => 'Sur', 'telefono' => '1144556677', 'tipo' => 'notebook'];

    $this->assertValidationError(fn() => $this->recibir($base + ['marca_id' => '999999', 'modelo_id' => EquipoService::MODELO_NUEVO . 'X1']), 'marca válida');
    $this->assertValidationError(fn() => $this->recibir($base + ['marca_id' => EquipoService::MODELO_NUEVO . 'Kodak', 'modelo_id' => EquipoService::MODELO_NUEVO]), 'modelo');
    $this->assertNull($this->make(\App\Repositories\MarcaRepository::class)->porNombre('Kodak'), 'La marca no quedó a medias');
  }

  public function testUnaOrdenSinItemsNoSeTerminaNiTienePresupuesto(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());
    $id = $this->recibir(['dni' => '30111222', 'equipo_id' => (string) $equipo]);
    $ordenes = $this->make(OrdenService::class);

    $ordenes->cambiarEstado($id, 'en_proceso');
    $this->assertValidationError(fn() => $ordenes->cambiarEstado($id, 'finalizado'), 'Para terminar el trabajo');
    $this->assertValidationError(fn() => $this->make(DocumentoService::class)->datos($id), 'todavía no tiene servicios ni repuestos');

    // Con los trabajos cargados, sí.
    $ordenes->guardar($equipo, [$this->crearServicio('Limpieza', 1000)], [], $id);
    $this->assertSame('finalizado', $ordenes->cambiarEstado($id, 'finalizado')->value);
  }
}
