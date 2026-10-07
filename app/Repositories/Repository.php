<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

/**
 * Base de los repositorios: acceso a datos vía PDO, sin lógica de negocio.
 */
abstract class Repository
{
  private const MYSQL_DUPLICATE = 1062;
  private const MYSQL_ROW_IS_REFERENCED = 1451;

  public function __construct(protected readonly PDO $db)
  {
  }

  /** @return list<array<string, mixed>> */
  protected function fetchAll(string $sql, array $params = []): array
  {
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
  }

  /** @return array<int|string, mixed> primera columna como clave, segunda como valor */
  protected function fetchPairs(string $sql, array $params = []): array
  {
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  /** @return array<string, mixed>|null */
  protected function fetchOne(string $sql, array $params = []): ?array
  {
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch() ?: null;
  }

  protected function execute(string $sql, array $params = []): int
  {
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
  }

  protected function insert(string $sql, array $params = []): int
  {
    $this->execute($sql, $params);

    return (int) $this->db->lastInsertId();
  }

  /**
   * Ejecuta $callback dentro de una transacción. Si ya hay una abierta, se suma a ella.
   *
   * @template T
   * @param callable(): T $callback
   * @return T
   */
  public function transaction(callable $callback): mixed
  {
    if ($this->db->inTransaction()) {
      return $callback();
    }

    $this->db->beginTransaction();
    try {
      $result = $callback();
      $this->db->commit();

      return $result;
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  /**
   * Ejecuta $callback con un candado de MySQL (GET_LOCK) por nombre: otro proceso que pida
   * el mismo candado espera a que termine. Sirve para "controlar y después guardar" sin que
   * dos pedidos simultáneos pasen el mismo control.
   *
   * @template T
   * @param callable(): T $callback
   * @return T
   */
  public function conCandado(string $nombre, callable $callback, int $espera = 10): mixed
  {
    $nombre = 'gestion:' . substr(hash('sha256', $nombre), 0, 40);
    $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
    $stmt->execute([$nombre, $espera]);
    if ((int) $stmt->fetchColumn() !== 1) {
      throw new \RuntimeException('El sistema está ocupado procesando otro pedido igual; probá de nuevo en unos segundos.');
    }

    try {
      return $callback();
    } finally {
      $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$nombre]);
    }
  }

  public static function isDuplicate(PDOException $e): bool
  {
    return (int) ($e->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE;
  }

  public static function isReferenced(PDOException $e): bool
  {
    return (int) ($e->errorInfo[1] ?? 0) === self::MYSQL_ROW_IS_REFERENCED;
  }
}
