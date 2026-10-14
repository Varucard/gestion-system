<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Repositories\ClienteRepository;
use App\Repositories\EquipoRepository;
use App\Repositories\OrdenRepository;
use App\Support\Validator;

/**
 * "Llegó un equipo": recibir un equipo en un solo paso.
 *
 * Con el DNI se sabe si el cliente ya está cargado y qué equipos tiene. Si el equipo no está,
 * en el mismo formulario se cargan el equipo y, si hace falta, el cliente. Al final se abre la
 * orden con la falla, los accesorios y el estado en que llega; los servicios y repuestos se
 * agregan después, cuando se sabe qué hay que hacer.
 */
final class RecepcionService
{
  public function __construct(
    private readonly EquipoRepository $equipos,
    private readonly ClienteRepository $clientes,
    private readonly OrdenRepository $ordenes,
    private readonly ClienteService $clienteService,
    private readonly EquipoService $equipoService,
    private readonly OrdenService $ordenService,
  ) {
  }

  /** @return array<string, mixed>|null el cliente con ese DNI, si ya está cargado */
  public function buscarDni(string $dni): ?array
  {
    $dni = preg_replace('/\D/', '', $dni);

    return preg_match('/^\d{6,8}$/', $dni) ? $this->clientes->porDni($dni) : null;
  }

  /**
   * Los equipos activos del cliente, cada uno con sus órdenes que siguen en el local.
   *
   * @return list<array<string, mixed>>
   */
  public function equiposDe(int $clienteId): array
  {
    return array_map(
      fn(array $e) => [...$e, 'abiertas' => $this->abiertas((int) $e['id'])],
      $this->equipos->activosPorCliente($clienteId),
    );
  }

  /**
   * Órdenes del equipo que siguen en el local: abiertas, o listas que nadie retiró.
   *
   * @return list<array<string, mixed>>
   */
  public function abiertas(int $equipoId): array
  {
    return array_values(array_filter(
      $this->ordenes->porEquipo($equipoId),
      fn(array $o) => OrdenService::editable(EstadoOrden::from($o['estado']))
        || ($o['estado'] === EstadoOrden::Finalizado->value && $o['entregado_en'] === null),
    ));
  }

  /**
   * Recibe el equipo y abre la orden. Todo o nada: si algo no valida, no queda ni el cliente
   * ni el equipo a medio cargar.
   *
   * @param array<string, mixed> $input dni, equipo_id (vacío: equipo nuevo), falla_reportada, accesorios,
   *                                    estado_ingreso, tecnico_id, turno_id, confirmar_otra; si el cliente es
   *                                    nuevo: nombre, apellido, telefono, email; si el equipo es nuevo: tipo,
   *                                    marca_id, modelo_id, numero_serie
   * @return int id de la orden creada
   */
  public function recibir(array $input): int
  {
    $dni = preg_replace('/\D/', '', (string) ($input['dni'] ?? ''));
    (new Validator())
      ->check((bool) preg_match('/^\d{6,8}$/', $dni), 'Escribí el DNI del cliente (solo números, de 6 a 8 dígitos).', 'dni')
      ->validate();

    return $this->ordenes->transaction(function () use ($input, $dni) {
      $equipoId = (int) ($input['equipo_id'] ?? 0);
      if ($equipoId > 0) {
        // Primero se bloquea el equipo (lectura con candado, antes de cualquier otra consulta de
        // la transacción): si dos personas reciben el mismo equipo a la vez, la segunda espera a
        // la primera y ve su orden abierta, en vez de abrir otra en paralelo.
        $this->equipos->bloquear($equipoId);
        $this->validarConocido($equipoId, $this->buscarDni($dni), (int) ($input['confirmar_otra'] ?? 0));
      } else {
        $equipoId = $this->registrarEquipo($input, $dni);
      }

      return $this->ordenService->guardar($equipoId, [], [], null, ((int) ($input['tecnico_id'] ?? 0)) ?: null, [
        'falla_reportada' => $input['falla_reportada'] ?? '',
        'accesorios' => $input['accesorios'] ?? '',
        'estado_ingreso' => $input['estado_ingreso'] ?? '',
        'turno_id' => $input['turno_id'] ?? '',
      ]);
    });
  }

  /**
   * Un equipo ya cargado: tiene que ser de ese cliente y estar activo. Si ya está en el local,
   * abrir otra orden se confirma con el número de la orden abierta que la persona vio
   * (confirmar_otra). Si mientras tanto se abrió otra, no coincide y se vuelve a preguntar.
   *
   * @param array<string, mixed>|null $cliente
   */
  private function validarConocido(int $equipoId, ?array $cliente, int $confirmarOtra): void
  {
    $equipo = $this->equipos->find($equipoId);
    (new Validator())
      ->check($equipo !== null && $cliente !== null && (int) $equipo['cliente_id'] === (int) $cliente['id'],
        'Ese equipo no es de este cliente. Elegí uno de sus equipos, o cargá uno nuevo.', 'equipo_id')
      ->validate();
    (new Validator())
      ->check($equipo['estado'] === 'activo', 'Ese equipo está dado de baja. Activalo desde su ficha para poder recibirlo.', 'equipo_id')
      ->validate();

    $abiertas = array_map('intval', array_column($this->abiertas($equipoId), 'id'));
    (new Validator())
      ->check($abiertas === [] || $confirmarOtra === max($abiertas), sprintf(
        'Este equipo ya tiene la orden #%d abierta. Seguí esa orden, o confirmá que querés abrir otra.',
        $abiertas === [] ? 0 : max($abiertas),
      ))
      ->validate();
  }

  /** Equipo que no estaba cargado: el cliente se reutiliza si su DNI ya existe. */
  private function registrarEquipo(array $input, string $dni): int
  {
    $cliente = $this->buscarDni($dni);
    // Cliente dado de baja que vuelve con un equipo nuevo: se lo reactiva (si no, el equipo quedaba
    // asignado a alguien que no aparece en los turnos ni en las órdenes).
    if ($cliente !== null && $cliente['estado'] !== 'activo') {
      $this->clienteService->alternarEstado((int) $cliente['id']);
    }
    $clienteId = $cliente !== null ? (int) $cliente['id'] : $this->clienteService->crear([
      'dni' => $dni,
      'nombre' => $input['nombre'] ?? '',
      'apellido' => $input['apellido'] ?? '',
      'telefono' => $input['telefono'] ?? '',
      'email' => $input['email'] ?? '',
    ]);

    return $this->equipoService->crear([
      'cliente_id' => $clienteId,
      'tipo' => $input['tipo'] ?? '',
      'marca_id' => $input['marca_id'] ?? '',
      'modelo_id' => $input['modelo_id'] ?? '',
      'numero_serie' => $input['numero_serie'] ?? '',
    ]);
  }
}
