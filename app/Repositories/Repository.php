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

  /** Transacciones anidadas abiertas (savepoints), para nombrarlos sin repetir. */
  private static int $anidadas = 0;

  /**
   * Lo que hay que hacer recién cuando la transacción se confirma (ver alConfirmar()), por
   * nivel: una pila con un nivel por cada transaction() abierta. Si un nivel se deshace,
   * lo suyo se descarta; si se confirma, pasa al de afuera (o se ejecuta, si era el último).
   *
   * @var list<list<callable(): void>>
   */
  private static array $alConfirmar = [];

  /**
   * Ejecuta $callback dentro de una transacción. Si ya hay una abierta, se anida con un
   * savepoint: si $callback falla se deshace solo lo suyo y el error sigue hacia arriba
   * (la transacción de afuera decide). Así "todo o nada" vale también por partes.
   *
   * @template T
   * @param callable(): T $callback
   * @return T
   */
  public function transaction(callable $callback): mixed
  {
    if ($this->db->inTransaction()) {
      $punto = 'anidada_' . ++self::$anidadas;
      $this->db->exec("SAVEPOINT {$punto}");
      self::$alConfirmar[] = [];
      try {
        $result = $callback();
        $this->db->exec("RELEASE SAVEPOINT {$punto}");
        self::confirmarNivel();

        return $result;
      } catch (\Throwable $e) {
        array_pop(self::$alConfirmar);
        try {
          $this->db->exec("ROLLBACK TO SAVEPOINT {$punto}");
        } catch (PDOException) {
          // MySQL ya deshizo la transacción entera (p. ej., un deadlock) y el savepoint no existe:
          // lo que importa es el error original.
        }
        throw $e;
      } finally {
        self::$anidadas--;
      }
    }

    $this->db->beginTransaction();
    self::$alConfirmar[] = [];
    try {
      $result = $callback();
      $this->db->commit();
      self::confirmarNivel();

      return $result;
    } catch (\Throwable $e) {
      array_pop(self::$alConfirmar);
      $this->db->rollBack();
      throw $e;
    }
  }

  /**
   * Hace $accion cuando se confirme la transacción en curso; sin transacción, en el momento.
   * Para lo que no se puede deshacer (una línea en el log de texto, un email): si la
   * operación se deshace, no queda registrado algo que no pasó.
   *
   * @param callable(): void $accion
   */
  public function alConfirmar(callable $accion): void
  {
    if (self::$alConfirmar === []) {
      $accion();

      return;
    }
    self::$alConfirmar[array_key_last(self::$alConfirmar)][] = $accion;
  }

  /** ¿Hay una transaction() abierta? (No cuenta una transacción abierta por fuera, p. ej. en los tests.) */
  public function enTransaccion(): bool
  {
    return self::$alConfirmar !== [];
  }

  /** Cierra el nivel confirmado: sus acciones pasan al nivel de afuera o, si era el último, se ejecutan. */
  private static function confirmarNivel(): void
  {
    $acciones = array_pop(self::$alConfirmar);
    if (self::$alConfirmar !== []) {
      array_push(self::$alConfirmar[array_key_last(self::$alConfirmar)], ...$acciones);

      return;
    }
    foreach ($acciones as $accion) {
      $accion();
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
    // Adentro de una transacción el candado se soltaría antes del COMMIT y otro pedido podría
    // pasar el mismo control sin ver lo guardado: tiene que ir por fuera.
    if ($this->enTransaccion()) {
      throw new \LogicException('conCandado() no se puede usar dentro de transaction(): el candado se soltaría antes de confirmar.');
    }
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
