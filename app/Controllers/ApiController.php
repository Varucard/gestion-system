<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\EstadoOrden;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\OrdenRepository;
use App\Services\PortalService;

/**
 * API para el sitio web del negocio (la sección "Seguí tu servicio"): consulta un trabajo con el
 * número de ticket y el DNI del cliente, y responde en JSON.
 *
 * La llama el servidor del sitio, no el navegador: así la clave (SEGUIMIENTO_API_KEY en .env)
 * no queda a la vista. Sin esa clave la API no existe (404). El servidor del sitio manda la IP
 * de quien consulta en X-Cliente-IP, para que el bloqueo por intentos fallidos sea por persona
 * y no por el servidor del sitio.
 */
final class ApiController extends Controller
{
  /** Las etapas de un trabajo, como se las nombra al cliente (las mismas del portal). */
  private const ETAPAS = ['recibido' => 'Recibido', 'en_reparacion' => 'En reparación', 'listo' => 'Listo para retirar', 'entregado' => 'Entregado'];

  public function __construct(
    View $view,
    Session $session,
    private readonly PortalService $portal,
    private readonly OrdenRepository $ordenes,
  ) {
    parent::__construct($view, $session);
  }

  public function seguimiento(Request $request): void
  {
    header('Cache-Control: no-store, private');
    $ip = $this->autorizar($request);
    $datos = $this->cuerpo($request);
    $dni = (string) ($datos['dni'] ?? '');
    $ticket = (string) ($datos['ticket'] ?? '');

    if ($this->portal->bloqueado($dni, $ip)) {
      $this->json(['error' => sprintf('Demasiadas consultas fallidas. Esperá %d minutos e intentá de nuevo.', PortalService::MINUTOS_BLOQUEO)], 429);
    }

    try {
      $resultado = $this->portal->consultarTicket($dni, $ticket, $ip);
    } catch (ValidationException $e) {
      $this->json(['error' => $e->getMessage(), 'campos' => $e->campos()], 422);
    } catch (NotFoundException $e) {
      $this->json(['error' => $e->getMessage()], 404);
    }

    $this->json($this->ticket($resultado));
  }

  /**
   * Lo que se devuelve de un trabajo: nada de datos de contacto ni notas internas.
   *
   * @param array{nombre: string, orden: array<string, mixed>, mostrarMontos: bool} $resultado
   * @return array<string, mixed>
   */
  private function ticket(array $resultado): array
  {
    $o = $resultado['orden'];
    $estado = EstadoOrden::from($o['estado']);
    $etapa = match (true) {
      $estado === EstadoOrden::Cancelado => 'cancelado',
      $o['entregado_en'] !== null => 'entregado',
      $estado === EstadoOrden::Finalizado => 'listo',
      $estado === EstadoOrden::EnProceso => 'en_reparacion',
      default => 'recibido',
    };
    $posicion = array_search($etapa, array_keys(self::ETAPAS), true);
    $esperandoRespuesta = $o['presupuesto_enviado'] !== null && $o['presupuesto_respuesta'] === null && \App\Services\OrdenService::editable($estado);

    return [
      'ticket' => str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT),
      'cliente' => $resultado['nombre'],
      'equipo' => [
        'tipo' => \App\Enums\TipoEquipo::etiqueta($o['tipo']),
        'descripcion' => equipo_texto($o, false),
        'numero_serie' => $o['numero_serie'],
      ],
      'ingreso' => substr((string) $o['created_at'], 0, 10),
      'etapa' => ['clave' => $etapa, 'nombre' => $etapa === 'cancelado' ? 'Cancelado' : self::ETAPAS[$etapa]],
      // Para dibujar el avance: cada etapa y si ya se pasó por ella (vacío si se canceló).
      'etapas' => $etapa === 'cancelado' ? [] : array_map(
        fn(string $clave, string $nombre, int $i) => ['clave' => $clave, 'nombre' => $nombre, 'hecha' => $i <= $posicion],
        array_keys(self::ETAPAS), self::ETAPAS, range(0, count(self::ETAPAS) - 1),
      ),
      'terminado' => $o['fecha_realizado'],
      'entregado' => $o['entregado_en'] !== null ? substr((string) $o['entregado_en'], 0, 10) : null,
      'falla' => $o['falla_reportada'],
      'trabajos' => array_values(array_filter(array_map('trim', explode(',', (string) $o['servicios'])))),
      'presupuesto' => [
        'estado' => $o['presupuesto_respuesta'] ?? ($esperandoRespuesta ? 'esperando_respuesta' : null),
        // El link de siempre del presupuesto: ahí el cliente lo ve y lo acepta.
        'link' => $esperandoRespuesta ? absolute_url('presupuesto/' . $this->ordenes->token((int) $o['id'])) : null,
      ],
      'montos' => $resultado['mostrarMontos'] && $etapa !== 'cancelado'
        ? ['total' => round((float) $o['total'], 2), 'saldo' => round(max(0.0, (float) $o['saldo']), 2)]
        : null,
      'portal' => absolute_url('seguimiento'),
    ];
  }

  /** Verifica la clave y devuelve la IP de quien consulta. */
  private function autorizar(Request $request): string
  {
    $clave = (string) Env::get('SEGUIMIENTO_API_KEY', '');
    if ($clave === '') {
      throw new NotFoundException('Página no encontrada.');
    }

    $recibida = (string) ($request->header('X-Api-Key') ?? preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization')));
    if (!hash_equals($clave, $recibida)) {
      $this->json(['error' => 'Clave de la API inválida.'], 401);
    }

    $ip = (string) $request->header('X-Cliente-IP');

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : Request::ip();
  }

  /** @return array<string, mixed> el cuerpo, en JSON o como formulario */
  private function cuerpo(Request $request): array
  {
    if ($request->input('dni') !== null || $request->input('ticket') !== null) {
      return ['dni' => $request->input('dni'), 'ticket' => $request->input('ticket')];
    }
    $json = json_decode((string) file_get_contents('php://input', length: 4096), true);

    return is_array($json) ? $json : [];
  }
}
