<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Aplica en orden los archivos database/migrations/NNNN_*.sql que todavía no
 * figuran en la tabla `migraciones`.
 *
 * En MySQL cada sentencia DDL se confirma sola, así que una migración que falla
 * a la mitad deja aplicadas las sentencias anteriores. Por eso se registra hasta
 * qué sentencia llegó (tabla `migraciones_parciales`) y, una vez corregido el
 * problema, el reintento continúa desde la sentencia que falló.
 */
final class Migrator
{
  public function __construct(
    private readonly PDO $db,
    private readonly string $directory,
  ) {
  }

  /** @return list<string> nombres de las migraciones aplicadas en esta ejecución */
  public function migrate(?callable $log = null): array
  {
    $this->db->exec(
      'CREATE TABLE IF NOT EXISTS migraciones (
         nombre varchar(190) NOT NULL PRIMARY KEY,
         aplicada_en timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $this->db->exec(
      'CREATE TABLE IF NOT EXISTS migraciones_parciales (
         nombre varchar(190) NOT NULL PRIMARY KEY,
         sentencias_aplicadas int NOT NULL
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $aplicadas = $this->db->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
    $parciales = $this->db->query('SELECT nombre, sentencias_aplicadas FROM migraciones_parciales')->fetchAll(PDO::FETCH_KEY_PAIR);
    $nuevas = [];

    foreach ($this->pendientes($aplicadas) as $nombre => $archivo) {
      $hechas = (int) ($parciales[$nombre] ?? 0);
      $log && $log($hechas > 0 ? "Retomando {$nombre} desde la sentencia " . ($hechas + 1) . '...' : "Aplicando {$nombre}...");

      $sentencias = self::sentencias((string) file_get_contents($archivo));
      foreach ($sentencias as $i => $sql) {
        if ($i < $hechas) {
          continue;
        }
        try {
          $this->db->exec($sql);
        } catch (PDOException $e) {
          $this->db->prepare(
            'INSERT INTO migraciones_parciales (nombre, sentencias_aplicadas) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE sentencias_aplicadas = VALUES(sentencias_aplicadas)'
          )->execute([$nombre, $i]);

          throw new RuntimeException(sprintf(
            'La migración %s falló en la sentencia %d de %d (las anteriores quedaron aplicadas; al reintentar se continúa desde esta): %s%sSentencia: %s',
            $nombre, $i + 1, count($sentencias), $e->getMessage(), PHP_EOL, mb_strimwidth($sql, 0, 300, '…'),
          ), 0, $e);
        }
      }

      $this->db->prepare('INSERT INTO migraciones (nombre) VALUES (?)')->execute([$nombre]);
      $this->db->prepare('DELETE FROM migraciones_parciales WHERE nombre = ?')->execute([$nombre]);
      $nuevas[] = $nombre;
    }

    return $nuevas;
  }

  /**
   * @param list<string> $aplicadas
   * @return array<string, string> nombre => ruta
   */
  private function pendientes(array $aplicadas): array
  {
    $archivos = glob($this->directory . '/[0-9][0-9][0-9][0-9]_*.sql') ?: [];
    sort($archivos);

    $pendientes = [];
    foreach ($archivos as $archivo) {
      $nombre = basename($archivo, '.sql');
      if (!in_array($nombre, $aplicadas, true)) {
        $pendientes[$nombre] = $archivo;
      }
    }

    return $pendientes;
  }

  /**
   * Divide un script en sentencias (terminadas en ";" al final de la línea),
   * descartando comentarios de línea.
   *
   * @return list<string>
   */
  public static function sentencias(string $script): array
  {
    $lineas = array_filter(
      preg_split('/\R/', $script),
      fn(string $l) => !str_starts_with(ltrim($l), '--')
    );

    $partes = preg_split('/;\s*$/m', implode("\n", $lineas));

    return array_values(array_filter(array_map('trim', $partes), fn(string $s) => $s !== ''));
  }
}
