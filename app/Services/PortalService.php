<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\ClienteRepository;
use App\Repositories\IntentoRepository;
use App\Repositories\OrdenRepository;
use App\Repositories\TurnoRepository;
use App\Repositories\EquipoRepository;
use App\Support\Validator;

/**
 * Portal público "Seguí tu equipo": el cliente consulta con su DNI (y, según
 * la configuración, el número de una de sus órdenes) el estado de sus
 * órdenes y sus próximos turnos. Solo se muestran datos no sensibles.
 * Desde el link de un presupuesto entra directo, sin DNI ni número de orden.
 */
final class PortalService
{
  private const AMBITO = 'portal';
  /** Consultas fallidas con el mismo DNI desde la misma IP; desde una IP; con un DNI desde cualquier IP. */
  public const MAX_INTENTOS = 10;
  public const MAX_INTENTOS_IP = 30;
  public const MAX_INTENTOS_DNI = 20;
  public const MINUTOS_BLOQUEO = 15;

  public function __construct(
    private readonly ConfiguracionService $configuracion,
    private readonly ClienteRepository $clientes,
    private readonly EquipoRepository $equipos,
    private readonly OrdenRepository $ordenes,
    private readonly TurnoRepository $turnos,
    private readonly IntentoRepository $intentos,
  ) {
  }

  /** @return array<string, mixed> */
  public function opciones(): array
  {
    return $this->configuracion->seccion('portal');
  }

  /** @return array<string, mixed> datos para la vista de resultado */
  public function consultar(string $dni, string $numeroOrden, string $ip): array
  {
    $opciones = $this->opciones();
    if (!$opciones['habilitado']) {
      throw new NotFoundException('La consulta en línea no está disponible.');
    }

    $dni = preg_replace('/\D/', '', $dni);
    $numeroOrden = (int) preg_replace('/\D/', '', $numeroOrden);

    // La consulta se anota antes de verificar y se borra si acierta (ver anotarYVerificar).
    if ($this->intentos->anotarYVerificar(self::AMBITO, $dni, $ip, self::MINUTOS_BLOQUEO, self::MAX_INTENTOS, self::MAX_INTENTOS_IP, self::MAX_INTENTOS_DNI)) {
      throw new ValidationException([sprintf('Demasiadas consultas fallidas. Esperá %d minutos e intentá de nuevo.', self::MINUTOS_BLOQUEO)]);
    }

    (new Validator())
      ->check((bool) preg_match('/^\d{6,8}$/', $dni), 'Ingresá un DNI válido (solo números).')
      ->check(!$opciones['requiere_orden'] || $numeroOrden > 0, 'Ingresá el número de una de tus órdenes.')
      ->validate();

    $cliente = $this->clientes->porDni($dni);
    $equipos = $cliente ? $this->equipos->porCliente((int) $cliente['id']) : [];
    $ordenes = $cliente ? $this->ordenes->porCliente((int) $cliente['id']) : [];
    $ordenValida = !$opciones['requiere_orden'] || in_array($numeroOrden, array_map('intval', array_column($ordenes, 'id')), true);

    if ($cliente === null || $cliente['estado'] !== 'activo' || !$ordenValida) {
      // Mismo mensaje en todos los casos: no revela si el DNI existe.
      throw new ValidationException(['No encontramos datos con esa combinación. Revisá el DNI y el número de orden o comunicate con nosotros.']);
    }

    $this->intentos->limpiar(self::AMBITO, $dni);

    return $this->resultado($cliente, $equipos, $opciones);
  }

  /** ¿Ese DNI (o esa IP) ya agotó las consultas fallidas? La API lo usa para responder 429. */
  public function bloqueado(string $dni, string $ip): bool
  {
    return $this->intentos->bloqueado(self::AMBITO, preg_replace('/\D/', '', $dni), $ip, self::MINUTOS_BLOQUEO, self::MAX_INTENTOS, self::MAX_INTENTOS_IP, self::MAX_INTENTOS_DNI);
  }

  /**
   * Un solo trabajo, por su número de ticket (el de la orden: "0013", "#13", "PCF-0013") y el DNI
   * del cliente. Es lo que consulta el sitio web por la API. Pide siempre los dos datos: el
   * número de orden es correlativo y, solo, dejaría recorrer los trabajos de todos. Comparte
   * con el portal el control de intentos fallidos.
   *
   * @return array{nombre: string, orden: array<string, mixed>, mostrarMontos: bool}
   */
  public function consultarTicket(string $dni, string $ticket, string $ip): array
  {
    if (!$this->opciones()['habilitado']) {
      throw new NotFoundException('La consulta en línea no está disponible.');
    }

    $dni = preg_replace('/\D/', '', $dni);
    $numero = (int) preg_replace('/\D/', '', $ticket);

    if ($this->intentos->anotarYVerificar(self::AMBITO, $dni, $ip, self::MINUTOS_BLOQUEO, self::MAX_INTENTOS, self::MAX_INTENTOS_IP, self::MAX_INTENTOS_DNI)) {
      throw new ValidationException([sprintf('Demasiadas consultas fallidas. Esperá %d minutos e intentá de nuevo.', self::MINUTOS_BLOQUEO)]);
    }

    (new Validator())
      ->check((bool) preg_match('/^\d{6,8}$/', $dni), 'Ingresá un DNI válido (solo números).', 'dni')
      ->check($numero > 0 && $numero < 1_000_000_000, 'Ingresá el número de ticket que figura en tu comprobante.', 'ticket')
      ->validate();

    $cliente = $this->clientes->porDni($dni);
    $orden = null;
    if ($cliente !== null && $cliente['estado'] === 'activo') {
      foreach ($this->ordenes->porCliente((int) $cliente['id']) as $fila) {
        if ((int) $fila['id'] === $numero) {
          // El listado trae saldo y trabajos; la cabecera, el equipo y el presupuesto.
          $orden = [...$fila, ...$this->ordenes->find($numero)];
        }
      }
    }

    if ($orden === null) {
      // Mismo mensaje en todos los casos: no revela si el DNI o el ticket existen.
      throw new ValidationException(['No encontramos un trabajo con ese ticket y ese DNI. Revisalos o comunicate con nosotros.']);
    }

    $this->intentos->limpiar(self::AMBITO, $dni);

    return [
      'nombre' => mb_convert_case(mb_strtolower((string) $cliente['nombre']), MB_CASE_TITLE),
      'orden' => $orden,
      'mostrarMontos' => (bool) $this->opciones()['mostrar_montos'],
    ];
  }

  /**
   * Acceso directo desde el link del presupuesto: el token ya identifica al cliente (le
   * llegó a su email), así que no se le pide DNI ni número de orden.
   *
   * @return array<string, mixed> datos para la vista de resultado
   */
  public function consultarPorOrden(int $ordenId): array
  {
    $opciones = $this->opciones();
    if (!$opciones['habilitado']) {
      throw new NotFoundException('La consulta en línea no está disponible.');
    }

    $orden = $this->ordenes->find($ordenId);
    $cliente = $orden ? $this->clientes->find((int) $orden['cliente_id']) : null;
    if ($cliente === null || $cliente['estado'] !== 'activo') {
      throw new NotFoundException('No encontramos datos para este link. Comunicate con nosotros.');
    }

    // Solo esa orden, su equipo y sus turnos: el link viaja en el QR del comprobante impreso y no
    // tiene que mostrar el resto del historial del cliente.
    $equipoId = (int) $orden['equipo_id'];
    $resultado = $this->resultado($cliente, array_values(array_filter(
      $this->equipos->porCliente((int) $cliente['id']),
      fn($x) => (int) $x['id'] === $equipoId
    )), $opciones);
    $resultado['ordenes'] = array_values(array_filter($this->ordenes->porCliente((int) $cliente['id']), fn($o) => (int) $o['id'] === $ordenId));
    $resultado['turnos'] = $this->turnosProximos($this->turnos->porEquipo($equipoId));

    return $resultado;
  }

  /**
   * @param array<string, mixed> $cliente
   * @param list<array<string, mixed>> $equipos
   * @param array<string, mixed> $opciones
   * @return array<string, mixed>
   */
  private function resultado(array $cliente, array $equipos, array $opciones): array
  {
    return [
      'nombre' => mb_convert_case(mb_strtolower((string) $cliente['nombre']), MB_CASE_TITLE),
      'equipos' => array_values(array_filter($equipos, fn($e) => $e['estado'] === 'activo')),
      'ordenes' => array_slice($this->ordenes->porCliente((int) $cliente['id']), 0, (int) $opciones['cantidad_ordenes']),
      'turnos' => $this->turnosProximos($this->turnos->porCliente((int) $cliente['id'])),
      'mostrarMontos' => (bool) $opciones['mostrar_montos'],
    ];
  }

  /**
   * @param list<array<string, mixed>> $turnos
   * @return list<array<string, mixed>> los que vienen, del más cercano al más lejano
   */
  private function turnosProximos(array $turnos): array
  {
    $hoy = date('Y-m-d');

    return array_reverse(array_values(array_filter(
      $turnos,
      fn($t) => $t['fecha'] >= $hoy && in_array($t['estado'], ['pendiente', 'confirmado'], true)
    )));
  }
}
