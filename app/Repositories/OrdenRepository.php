<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\EstadoOrden;
use App\Models\Orden;
use App\Support\ConsultaPaginada;

final class OrdenRepository extends Repository
{
  /** Listado con cliente, equipo y resumen de ítems. */
  public function all(): array
  {
    return $this->listado('', []);
  }

  /**
   * Listado paginado en el servidor (DataTables). Filtro opcional: estado.
   *
   * @param array<string, mixed> $peticion
   */
  public function paginar(array $peticion): array
  {
    $saldo = 'o.total - COALESCE((SELECT SUM(pg.monto) FROM pagos pg WHERE pg.orden_id = o.id), 0)';
    $consulta = new ConsultaPaginada(
      "SELECT o.id, o.total, o.estado, o.created_at, o.presupuesto_respuesta, {$saldo} AS saldo,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente,
              CONCAT(ma.nombre, ' ', mo.nombre, IFNULL(CONCAT(' · S/N ', eq.numero_serie), '')) AS equipo,
              (SELECT CONCAT(pm.apellido, ', ', pm.nombre) FROM empleados em
                 INNER JOIN personas pm ON pm.id = em.persona_id WHERE em.id = o.tecnico_id) AS tecnico,
              (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ')
                 FROM ordenes_servicios os INNER JOIN servicios s ON s.id = os.servicio_id WHERE os.orden_id = o.id) AS servicios,
              (SELECT GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ')
                 FROM ordenes_servicios os INNER JOIN repuestos r ON r.id = os.repuesto_id WHERE os.orden_id = o.id) AS repuestos
         FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id
         INNER JOIN clientes c ON c.id = o.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id",
      [
        ['sql' => 'o.id', 'buscar' => true],
        ['sql' => "CONCAT(p.apellido, ', ', p.nombre)", 'buscar' => true],
        ['sql' => "CONCAT(ma.nombre, ' ', mo.nombre, ' ', IFNULL(eq.numero_serie, ''))", 'buscar' => true],
        ['sql' => null],
        ['sql' => 'o.total'],
        ['sql' => $saldo],
        ['sql' => 'o.created_at'],
        ['sql' => 'o.estado'],
        ['sql' => null],
      ],
      'o.id DESC',
    );

    $estado = (string) ($peticion['estado'] ?? '');
    $filtros = match ($estado) {
      '' => [],
      'abiertas' => [["o.estado IN ('pendiente', 'en_proceso')", []]],
      'con_saldo' => [["o.estado <> 'cancelado' AND {$saldo} > 0", []]],
      default => [['o.estado = ?', [$estado]]],
    };

    return $consulta->ejecutar($this->db, $peticion, $filtros);
  }

  /** @return list<array<string, mixed>> */
  public function porCliente(int $clienteId): array
  {
    return $this->listado('WHERE o.cliente_id = ?', [$clienteId]);
  }

  /** @return list<array<string, mixed>> */
  public function porEquipo(int $equipoId): array
  {
    return $this->listado('WHERE o.equipo_id = ?', [$equipoId]);
  }

  /** @return list<array<string, mixed>> */
  private function listado(string $where, array $params): array
  {
    return $this->fetchAll(
      "SELECT o.id, o.total, o.estado, o.created_at,
              o.total - COALESCE((SELECT SUM(pg.monto) FROM pagos pg WHERE pg.orden_id = o.id), 0) AS saldo,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente,
              CONCAT(ma.nombre, ' ', mo.nombre, IFNULL(CONCAT(' · S/N ', eq.numero_serie), '')) AS equipo,
              (SELECT CONCAT(pm.apellido, ', ', pm.nombre) FROM empleados em
                 INNER JOIN personas pm ON pm.id = em.persona_id WHERE em.id = o.tecnico_id) AS tecnico,
              (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ')
                 FROM ordenes_servicios os INNER JOIN servicios s ON s.id = os.servicio_id
                WHERE os.orden_id = o.id) AS servicios,
              (SELECT GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ')
                 FROM ordenes_servicios os INNER JOIN repuestos r ON r.id = os.repuesto_id
                WHERE os.orden_id = o.id) AS repuestos
         FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id
         INNER JOIN clientes c ON c.id = o.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
        {$where}
        ORDER BY o.id DESC",
      $params
    );
  }

  /** Cabecera de la orden con datos del equipo. */
  public function find(int $id): ?array
  {
    return $this->fetchOne(
      "SELECT o.*, eq.tipo, eq.numero_serie,
              ma.nombre AS marca, mo.nombre AS modelo,
              CONCAT(pm.apellido, ', ', pm.nombre) AS tecnico
         FROM ordenes o
         LEFT JOIN empleados em ON em.id = o.tecnico_id
         LEFT JOIN personas pm ON pm.id = em.persona_id
         INNER JOIN equipos eq ON eq.id = o.equipo_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
        WHERE o.id = ?",
      [$id]
    );
  }

  /** Ítems de la orden con su descripción. */
  public function items(int $ordenId): array
  {
    return $this->fetchAll(
      'SELECT os.servicio_id, os.repuesto_id, os.cantidad, os.precio_unitario, os.costo,
              s.nombre AS servicio_nombre, r.nombre AS repuesto_nombre
         FROM ordenes_servicios os
         LEFT JOIN servicios s ON s.id = os.servicio_id
         LEFT JOIN repuestos r ON r.id = os.repuesto_id
        WHERE os.orden_id = ?
        ORDER BY os.repuesto_id IS NOT NULL, s.nombre, r.nombre',
      [$ordenId]
    );
  }

  /** Inserta o actualiza la orden y reemplaza sus ítems, todo en una transacción. */
  public function save(Orden $orden): int
  {
    return $this->transaction(function () use ($orden) {
      if ($orden->id === null) {
        $id = $this->insert(
          'INSERT INTO ordenes (equipo_id, cliente_id, tecnico_id, turno_id, estado, total, falla_reportada, accesorios, estado_ingreso,
                                diagnostico, trabajo_realizado, notas_internas, proximo_mantenimiento_fecha)
           VALUES (?, (SELECT cliente_id FROM equipos WHERE id = ?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [$orden->equipoId, $orden->equipoId, $orden->tecnicoId, $orden->turnoId, $orden->estado->value, $orden->total(), ...$this->detalle($orden)]
        );
      } else {
        $id = $orden->id;
        // Si cambia el próximo mantenimiento, se vuelve a habilitar su aviso. Si cambia el equipo,
        // la orden pasa a ser del dueño actual de ese equipo (cliente_id va antes que equipo_id
        // porque MySQL aplica las asignaciones en orden).
        $this->execute(
          'UPDATE ordenes SET cliente_id = IF(equipo_id = ?, cliente_id, (SELECT cliente_id FROM equipos WHERE id = ?)),
                  equipo_id = ?, tecnico_id = ?, total = ?, falla_reportada = ?, accesorios = ?, estado_ingreso = ?,
                  diagnostico = ?, trabajo_realizado = ?, notas_internas = ?,
                  proximo_mantenimiento_avisado = IF(proximo_mantenimiento_fecha <=> ?, proximo_mantenimiento_avisado, NULL),
                  proximo_mantenimiento_fecha = ?
            WHERE id = ?',
          [
            $orden->equipoId, $orden->equipoId, $orden->equipoId, $orden->tecnicoId, $orden->total(),
            ...array_slice($this->detalle($orden), 0, -1),
            $orden->proximoMantenimientoFecha, $orden->proximoMantenimientoFecha, $id,
          ]
        );
        $this->execute('DELETE FROM ordenes_servicios WHERE orden_id = ?', [$id]);
      }

      $stmt = $this->db->prepare(
        'INSERT INTO ordenes_servicios (orden_id, servicio_id, repuesto_id, cantidad, precio_unitario, costo)
         VALUES (?, ?, ?, ?, ?, ?)'
      );
      foreach ($orden->items as $item) {
        $stmt->execute([$id, $item->servicioId, $item->repuestoId, $item->cantidad, $item->precioUnitario, $item->subtotal()]);
      }

      return $id;
    });
  }

  /** @return list<mixed> */
  private function detalle(Orden $orden): array
  {
    return [
      $orden->fallaReportada, $orden->accesorios, $orden->estadoIngreso, $orden->diagnostico,
      $orden->trabajoRealizado, $orden->notasInternas, $orden->proximoMantenimientoFecha,
    ];
  }

  /** Datos de contacto y de la orden para armar avisos al cliente. */
  public function contacto(int $id): ?array
  {
    return $this->fetchOne(
      "SELECT o.id, o.total, o.estado, o.token, o.proximo_mantenimiento_fecha,
              p.nombre AS cliente_nombre, p.email AS cliente_email, c.telefono AS cliente_telefono,
              eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo
         FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id
         INNER JOIN clientes c ON c.id = o.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
        WHERE o.id = ?",
      [$id]
    );
  }

  /** Token del link público del presupuesto (se genera la primera vez). */
  public function token(int $id): string
  {
    $this->execute('UPDATE ordenes SET token = ? WHERE id = ? AND token IS NULL', [bin2hex(random_bytes(32)), $id]);

    return (string) $this->fetchOne('SELECT token FROM ordenes WHERE id = ?', [$id])['token'];
  }

  /** Días que el link del presupuesto sigue abriendo desde su último envío (o desde que se creó la orden). */
  public const DIAS_LINK = 60;

  public function idPorToken(string $token): ?int
  {
    $fila = $this->fetchOne(
      'SELECT id FROM ordenes WHERE token = ? AND COALESCE(presupuesto_enviado, created_at) >= NOW() - INTERVAL ' . self::DIAS_LINK . ' DAY',
      [$token]
    );

    return $fila ? (int) $fila['id'] : null;
  }

  /**
   * Un reenvío conserva la aceptación (la orden no cambió: si cambia, la respuesta se anula
   * al guardarla) y habilita responder de nuevo si había sido rechazado.
   */
  public function registrarPresupuestoEnviado(int $id): void
  {
    $this->execute(
      "UPDATE ordenes SET presupuesto_enviado = NOW(),
              presupuesto_respuesta_en = IF(presupuesto_respuesta = 'aceptado', presupuesto_respuesta_en, NULL),
              presupuesto_respuesta = IF(presupuesto_respuesta = 'aceptado', presupuesto_respuesta, NULL)
        WHERE id = ?",
      [$id]
    );
  }

  public function anularRespuestaPresupuesto(int $id): void
  {
    $this->execute('UPDATE ordenes SET presupuesto_respuesta = NULL, presupuesto_respuesta_en = NULL WHERE id = ?', [$id]);
  }

  /**
   * Estado y bandera de stock de la orden, bloqueando la fila hasta el fin de la transacción
   * en curso (debe llamarse dentro de una).
   *
   * @return array{estado: string, stock_descontado: int|string}|null
   */
  public function bloquear(int $id): ?array
  {
    return $this->fetchOne('SELECT estado, stock_descontado FROM ordenes WHERE id = ? FOR UPDATE', [$id]);
  }

  public function registrarRespuestaPresupuesto(int $id, string $respuesta): void
  {
    $this->execute('UPDATE ordenes SET presupuesto_respuesta = ?, presupuesto_respuesta_en = NOW() WHERE id = ?', [$respuesta, $id]);
  }

  /**
   * Órdenes finalizadas con próximo mantenimiento entre $desde y $hasta, sin avisar,
   * que sean la última orden del equipo (si volvió a entrar, ya no se avisa).
   *
   * @return list<int>
   */
  public function mantenimientosParaAvisar(string $desde, string $hasta): array
  {
    return array_map('intval', array_column($this->fetchAll(
      "SELECT o.id FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id AND eq.estado = 'activo'
         INNER JOIN clientes c ON c.id = o.cliente_id AND c.id = eq.cliente_id AND c.estado = 'activo'
        WHERE o.estado = 'finalizado' AND o.proximo_mantenimiento_avisado IS NULL
          AND o.proximo_mantenimiento_fecha BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM ordenes o2 WHERE o2.equipo_id = o.equipo_id AND o2.id > o.id AND o2.estado <> 'cancelado')
        ORDER BY o.proximo_mantenimiento_fecha",
      [$desde, $hasta]
    ), 'id'));
  }

  /** Reserva el aviso de mantenimiento (evita duplicados). */
  public function reservarAvisoMantenimiento(int $id): bool
  {
    return $this->execute('UPDATE ordenes SET proximo_mantenimiento_avisado = NOW() WHERE id = ? AND proximo_mantenimiento_avisado IS NULL', [$id]) === 1;
  }

  public function liberarAvisoMantenimiento(int $id): void
  {
    $this->execute('UPDATE ordenes SET proximo_mantenimiento_avisado = NULL WHERE id = ?', [$id]);
  }

  /** @return list<array<string, mixed>> mantenimientos a vencer en los próximos días (panel) */
  public function mantenimientosProximos(int $dias): array
  {
    return $this->fetchAll(
      "SELECT o.id, o.proximo_mantenimiento_fecha, o.proximo_mantenimiento_avisado, eq.id AS equipo_id,
              eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente
         FROM ordenes o
         INNER JOIN equipos eq ON eq.id = o.equipo_id AND eq.estado = 'activo'
         INNER JOIN clientes c ON c.id = eq.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
        WHERE o.estado = 'finalizado' AND o.proximo_mantenimiento_fecha BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY
          AND NOT EXISTS (SELECT 1 FROM ordenes o2 WHERE o2.equipo_id = o.equipo_id AND o2.id > o.id AND o2.estado <> 'cancelado')
        ORDER BY o.proximo_mantenimiento_fecha",
      [$dias]
    );
  }

  public function setStockDescontado(int $id, bool $descontado): void
  {
    $this->execute('UPDATE ordenes SET stock_descontado = ? WHERE id = ?', [(int) $descontado, $id]);
  }

  public function setEstado(int $id, EstadoOrden $estado): void
  {
    $this->execute(
      'UPDATE ordenes SET estado = ?, fecha_realizado = ? WHERE id = ?',
      [$estado->value, $estado === EstadoOrden::Finalizado ? date('Y-m-d') : null, $id]
    );
  }
}
