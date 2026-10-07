<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\EstadoTurno;
use App\Models\Turno;
use App\Support\ConsultaPaginada;

final class TurnoRepository extends Repository
{
  /**
   * Una reserva "enviando" que quedó así más de 30 minutos es de un proceso que se cortó
   * a mitad del envío: se vuelve a intentar en vez de dejar el turno sin recordatorio.
   */
  public const DIAS_LINK = 30;

  private const RESERVA_VENCIDA = "(recordatorio_canal = 'enviando' AND recordatorio_enviado < NOW() - INTERVAL 30 MINUTE)";

  /** @return list<array<string, mixed>> */
  public function all(): array
  {
    return $this->listado('', []);
  }

  /**
   * Listado paginado. Filtros opcionales: estado, periodo (proximos | pasados).
   *
   * @param array<string, mixed> $peticion
   */
  public function paginar(array $peticion): array
  {
    $consulta = new ConsultaPaginada(
      "SELECT t.id, t.cliente_id, t.equipo_id, t.fecha, t.hora, t.descripcion, t.estado,
              t.recordatorio_enviado, t.recordatorio_canal, t.token, t.confirmacion_enviada,
              t.respuesta_cliente, t.respuesta_en,
              eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo,
              p.nombre AS cliente_nombre, p.email AS cliente_email, c.telefono AS cliente_telefono,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente,
              CONCAT(ma.nombre, ' ', mo.nombre, IFNULL(CONCAT(' · S/N ', eq.numero_serie), '')) AS equipo
         FROM turnos t
         INNER JOIN clientes c ON c.id = t.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN equipos eq ON eq.id = t.equipo_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id",
      [
        ['sql' => "CONCAT(t.fecha, ' ', t.hora)"],
        ['sql' => "CONCAT(p.apellido, ', ', p.nombre)", 'buscar' => true],
        ['sql' => "CONCAT(ma.nombre, ' ', mo.nombre, ' ', IFNULL(eq.numero_serie, ''))", 'buscar' => true],
        ['sql' => 't.descripcion', 'buscar' => true],
        ['sql' => 't.estado'],
        ['sql' => null],
      ],
      't.fecha DESC, t.hora DESC',
    );

    $filtros = [];
    if (in_array($peticion['estado'] ?? '', array_map(fn(EstadoTurno $e) => $e->value, EstadoTurno::cases()), true)) {
      $filtros[] = ['t.estado = ?', [$peticion['estado']]];
    }
    if (($peticion['periodo'] ?? '') === 'proximos') {
      $filtros[] = ['t.fecha >= CURDATE()', []];
    } elseif (($peticion['periodo'] ?? '') === 'pasados') {
      $filtros[] = ['t.fecha < CURDATE()', []];
    }

    return $consulta->ejecutar($this->db, $peticion, $filtros);
  }

  /** @return list<array<string, mixed>> */
  public function porCliente(int $clienteId): array
  {
    return $this->listado('WHERE t.cliente_id = ?', [$clienteId]);
  }

  /** @return list<array<string, mixed>> */
  public function porEquipo(int $equipoId): array
  {
    return $this->listado('WHERE t.equipo_id = ?', [$equipoId]);
  }

  /** @return list<array<string, mixed>> */
  private function listado(string $where, array $params): array
  {
    return $this->fetchAll(
      "SELECT t.id, t.cliente_id, t.equipo_id, t.fecha, t.hora, t.descripcion, t.estado,
              t.recordatorio_enviado, t.recordatorio_canal, t.token, t.confirmacion_enviada,
              t.respuesta_cliente, t.respuesta_en,
              eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo,
              p.nombre AS cliente_nombre, p.email AS cliente_email, c.telefono AS cliente_telefono,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente,
              CONCAT(ma.nombre, ' ', mo.nombre, IFNULL(CONCAT(' · S/N ', eq.numero_serie), '')) AS equipo
         FROM turnos t
         INNER JOIN clientes c ON c.id = t.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN equipos eq ON eq.id = t.equipo_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
        {$where}
        ORDER BY t.fecha DESC, t.hora DESC",
      $params
    );
  }

  /** Turno con los datos de contacto del cliente y del equipo. */
  public function detalle(int $id): ?array
  {
    return $this->listado('WHERE t.id = ?', [$id])[0] ?? null;
  }

  /** @return list<array<string, mixed>> turnos activos entre dos fechas */
  public function activosEntre(string $desde, string $hasta): array
  {
    return array_reverse($this->listado(
      "WHERE t.fecha BETWEEN ? AND ? AND t.estado NOT IN ('cancelado', 'no_asistio')",
      [$desde, $hasta]
    ));
  }

  /** @return list<array<string, mixed>> turnos activos de una fecha, por hora */
  public function delDia(string $fecha): array
  {
    return array_reverse($this->listado(
      "WHERE t.fecha = ? AND t.estado NOT IN ('cancelado', 'no_asistio')",
      [$fecha]
    ));
  }

  public function registrarRecordatorio(int $id, string $canal): void
  {
    $this->execute('UPDATE turnos SET recordatorio_enviado = NOW(), recordatorio_canal = ? WHERE id = ?', [$canal, $id]);
  }

  /**
   * Reserva el envío del recordatorio automático (evita duplicados si la tarea
   * corre dos veces a la vez). Devuelve false si ya estaba reservado.
   */
  public function reservarRecordatorio(int $id): bool
  {
    return $this->execute(
      "UPDATE turnos SET recordatorio_enviado = NOW(), recordatorio_canal = 'enviando' WHERE id = ? AND (recordatorio_enviado IS NULL OR " . self::RESERVA_VENCIDA . ')',
      [$id]
    ) === 1;
  }

  public function liberarRecordatorio(int $id): void
  {
    $this->execute('UPDATE turnos SET recordatorio_enviado = NULL, recordatorio_canal = NULL WHERE id = ?', [$id]);
  }

  /**
   * Turnos activos entre mañana y $hasta (inclusive) sin recordatorio enviado.
   *
   * @return list<int>
   */
  public function pendientesDeRecordatorio(string $desde, string $hasta): array
  {
    return array_map('intval', array_column($this->fetchAll(
      "SELECT id FROM turnos
        WHERE fecha BETWEEN ? AND ? AND estado IN ('pendiente', 'confirmado')
          AND (recordatorio_enviado IS NULL OR " . self::RESERVA_VENCIDA . ")
        ORDER BY fecha, hora",
      [$desde, $hasta]
    ), 'id'));
  }

  /** Devuelve el token del turno, generándolo si todavía no tiene. */
  public function token(int $id): string
  {
    $actual = $this->fetchOne('SELECT token FROM turnos WHERE id = ?', [$id])['token'] ?? null;
    if ($actual) {
      return $actual;
    }

    $token = bin2hex(random_bytes(32));
    $this->execute('UPDATE turnos SET token = ? WHERE id = ? AND token IS NULL', [$token, $id]);

    return $this->fetchOne('SELECT token FROM turnos WHERE id = ?', [$id])['token'];
  }

  /**
   * Turno del link público. El link vence {@see self::DIAS_LINK} días después de la fecha
   * del turno: después ya no sirve para nada y no conviene que siga mostrando datos.
   */
  public function porToken(string $token): ?array
  {
    $fila = $this->fetchOne('SELECT id FROM turnos WHERE token = ? AND fecha >= CURDATE() - INTERVAL ' . self::DIAS_LINK . ' DAY', [$token]);

    return $fila ? $this->detalle((int) $fila['id']) : null;
  }

  public function registrarConfirmacionEnviada(int $id): void
  {
    $this->execute('UPDATE turnos SET confirmacion_enviada = NOW() WHERE id = ?', [$id]);
  }

  /** Respuesta del cliente desde el link: confirma o cancela. */
  public function registrarRespuesta(int $id, EstadoTurno $estado): void
  {
    $this->execute(
      'UPDATE turnos SET estado = ?, respuesta_cliente = ?, respuesta_en = NOW() WHERE id = ?',
      [$estado->value, $estado === EstadoTurno::Confirmado ? 'confirmado' : 'cancelado', $id]
    );
  }

  /** Al reprogramar se pide de nuevo la confirmación del cliente. */
  public function reiniciarConfirmacion(int $id): void
  {
    $this->execute(
      'UPDATE turnos SET respuesta_cliente = NULL, respuesta_en = NULL, recordatorio_enviado = NULL, recordatorio_canal = NULL WHERE id = ?',
      [$id]
    );
  }

  /** Cantidad de turnos activos en ese día y hora (excluyendo uno). */
  public function ocupados(string $fecha, string $hora, ?int $exceptoId = null): int
  {
    $liberan = array_map(
      fn(EstadoTurno $e) => $e->value,
      array_values(array_filter(EstadoTurno::cases(), fn(EstadoTurno $e) => $e->liberaHorario()))
    );
    $placeholders = implode(',', array_fill(0, count($liberan), '?'));

    return (int) $this->fetchOne(
      "SELECT COUNT(*) AS total FROM turnos WHERE fecha = ? AND hora = ? AND id <> ? AND estado NOT IN ({$placeholders})",
      [$fecha, $hora, $exceptoId ?? 0, ...$liberan]
    )['total'];
  }

  /** @return array<string, mixed>|null */
  public function find(int $id): ?array
  {
    return $this->fetchOne(
      'SELECT id, cliente_id, equipo_id, fecha, hora, descripcion, estado FROM turnos WHERE id = ?',
      [$id]
    );
  }

  public function save(Turno $turno): int
  {
    $params = [
      $turno->clienteId, $turno->equipoId, $turno->fecha, $turno->hora,
      $turno->descripcion, $turno->estado->value,
    ];

    if ($turno->id === null) {
      return $this->insert(
        'INSERT INTO turnos (cliente_id, equipo_id, fecha, hora, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?)',
        $params
      );
    }

    $this->execute(
      'UPDATE turnos SET cliente_id = ?, equipo_id = ?, fecha = ?, hora = ?, descripcion = ?, estado = ? WHERE id = ?',
      [...$params, $turno->id]
    );

    return $turno->id;
  }

  public function setEstado(int $id, EstadoTurno $estado): void
  {
    $this->execute('UPDATE turnos SET estado = ? WHERE id = ?', [$estado->value, $id]);
  }

  public function delete(int $id): void
  {
    $this->execute('DELETE FROM turnos WHERE id = ?', [$id]);
  }
}
