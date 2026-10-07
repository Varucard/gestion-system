<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Paginación, búsqueda y orden del lado del servidor para DataTables.
 *
 * Recibe la consulta base y las columnas (en el mismo orden que la tabla) y
 * responde al protocolo de DataTables (draw, start, length, search, order).
 * Todo valor del usuario va por parámetros; los nombres de columna salen de
 * la definición, nunca de la petición.
 */
final class ConsultaPaginada
{
  private const MAXIMO_POR_PAGINA = 100;

  /**
   * @param string $select  SELECT … FROM … JOIN … (sin WHERE ni ORDER BY)
   * @param list<array{sql: ?string, buscar?: bool}> $columnas  expresión SQL de cada columna (null = no ordenable)
   * @param string $orden  ORDER BY por defecto
   */
  public function __construct(
    private readonly string $select,
    private readonly array $columnas,
    private readonly string $orden,
  ) {
  }

  /**
   * @param array<string, mixed> $peticion parámetros de DataTables ($_GET)
   * @param list<array{0: string, 1: list<mixed>}> $filtros condiciones extra [sql, params]
   * @return array{draw: int, recordsTotal: int, recordsFiltered: int, filas: list<array<string, mixed>>}
   */
  public function ejecutar(PDO $db, array $peticion, array $filtros = []): array
  {
    [$whereFiltros, $paramsFiltros] = self::unir($filtros);

    $busqueda = trim((string) ($peticion['search']['value'] ?? ''));
    $condiciones = $filtros;
    if ($busqueda !== '') {
      $condiciones[] = $this->condicionBusqueda($busqueda);
    }
    [$where, $params] = self::unir($condiciones);

    $total = $this->contar($db, $whereFiltros, $paramsFiltros);
    $filtrados = $busqueda === '' ? $total : $this->contar($db, $where, $params);

    $inicio = max(0, (int) ($peticion['start'] ?? 0));
    $cantidad = (int) ($peticion['length'] ?? 25);
    $cantidad = $cantidad <= 0 ? self::MAXIMO_POR_PAGINA : min($cantidad, self::MAXIMO_POR_PAGINA);

    $stmt = $db->prepare("{$this->select} {$where} ORDER BY {$this->ordenSolicitado($peticion)} LIMIT {$cantidad} OFFSET {$inicio}");
    $stmt->execute($params);

    return [
      'draw' => (int) ($peticion['draw'] ?? 0),
      'recordsTotal' => $total,
      'recordsFiltered' => $filtrados,
      'filas' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
  }

  private function contar(PDO $db, string $where, array $params): int
  {
    $stmt = $db->prepare("SELECT COUNT(*) FROM ({$this->select} {$where}) AS t");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
  }

  /** Cada palabra buscada tiene que aparecer en alguna de las columnas buscables. */
  private function condicionBusqueda(string $busqueda): array
  {
    $columnas = array_values(array_filter(
      array_map(fn(array $c) => ($c['buscar'] ?? false) ? $c['sql'] : null, $this->columnas)
    ));
    $partes = [];
    $params = [];

    foreach (array_slice(preg_split('/\s+/', $busqueda), 0, 5) as $palabra) {
      $like = '%' . addcslashes($palabra, '%_\\') . '%';
      $partes[] = '(' . implode(' OR ', array_map(fn(string $sql) => "{$sql} LIKE ?", $columnas)) . ')';
      array_push($params, ...array_fill(0, count($columnas), $like));
    }

    return [implode(' AND ', $partes), $params];
  }

  private function ordenSolicitado(array $peticion): string
  {
    $indice = $peticion['order'][0]['column'] ?? null;
    $sql = is_numeric($indice) ? ($this->columnas[(int) $indice]['sql'] ?? null) : null;

    if ($sql === null) {
      return $this->orden;
    }

    $direccion = strtolower((string) ($peticion['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

    return "{$sql} {$direccion}, {$this->orden}";
  }

  /**
   * @param list<array{0: string, 1: list<mixed>}> $condiciones
   * @return array{0: string, 1: list<mixed>}
   */
  private static function unir(array $condiciones): array
  {
    if ($condiciones === []) {
      return ['', []];
    }

    return [
      'WHERE ' . implode(' AND ', array_map(fn(array $c) => "({$c[0]})", $condiciones)),
      array_merge(...array_map(fn(array $c) => $c[1], $condiciones)),
    ];
  }
}
