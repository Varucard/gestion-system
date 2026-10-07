<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use DateTimeImmutable;
use PDO;
use PharData;
use RuntimeException;
use Throwable;

/**
 * Backups de la base de datos (volcado SQL comprimido, restaurable con
 * `mysql` o scripts/restore.sh) y de los archivos (imágenes y configuración).
 *
 * Se guardan en storage/backups. La tarea periódica genera uno por día a
 * partir de la hora configurada y conserva los últimos N. Si BACKUP_COPIA_DIR
 * apunta a otra carpeta (un disco externo o una carpeta sincronizada con la
 * nube, montada en el contenedor), además se copian ahí.
 */
final class BackupService
{
  private const FILAS_POR_INSERT = 200;
  public const PATRON = '/^(db|archivos)_\d{4}-\d{2}-\d{2}_\d{6}\.(sql\.gz|tar\.gz)$/';

  private readonly string $directorio;
  private readonly string $raiz;

  public function __construct(
    private readonly PDO $db,
    private readonly ConfiguracionService $configuracion,
    private readonly Logger $logger,
    private readonly Auditor $auditor,
    ?string $raiz = null,
  ) {
    $this->raiz = $raiz ?? dirname(__DIR__, 2);
    $this->directorio = $this->raiz . '/storage/backups';
  }

  /** ¿Corresponde hacer el backup automático ahora? (habilitado, pasada la hora y sin backup de hoy) */
  public function corresponde(DateTimeImmutable $ahora): bool
  {
    $config = $this->configuracion->seccion('backups');

    return $config['habilitado']
      && $ahora->format('H:i') >= $config['hora']
      && glob($this->directorio . '/db_' . $ahora->format('Y-m-d') . '_*.sql.gz') === [];
  }

  /**
   * Genera el backup de la base y de los archivos. Devuelve los nombres creados.
   *
   * @return list<string>
   */
  public function generar(bool $manual = false): array
  {
    if (!is_dir($this->directorio) && !mkdir($this->directorio, 0775, true) && !is_dir($this->directorio)) {
      throw new RuntimeException('No se pudo crear la carpeta de backups.');
    }

    $sello = date('Y-m-d_His');
    $creados = [$this->volcarBase("db_{$sello}.sql.gz")];
    if (($archivos = $this->empaquetarArchivos("archivos_{$sello}.tar.gz")) !== null) {
      $creados[] = $archivos;
    }

    $conservar = (int) $this->configuracion->seccion('backups')['conservar'];
    $borrados = $this->rotar($conservar);
    $this->copiarFueraDelServidor($creados, $conservar);
    $this->logger->info('Backup generado: {archivos} ({borrados} viejos borrados)', ['archivos' => implode(', ', $creados), 'borrados' => $borrados]);
    if ($manual) {
      $this->auditor->registrar('backup', 'sistema', null, 'Backup manual generado: ' . implode(', ', $creados));
    }

    return $creados;
  }

  /** @return list<array{nombre: string, tamanio: int, fecha: string}> del más nuevo al más viejo */
  public function listar(): array
  {
    $archivos = [];
    foreach (glob($this->directorio . '/*.gz') ?: [] as $ruta) {
      if (preg_match(self::PATRON, basename($ruta))) {
        $archivos[] = ['nombre' => basename($ruta), 'tamanio' => (int) filesize($ruta), 'fecha' => date('Y-m-d H:i', (int) filemtime($ruta))];
      }
    }
    usort($archivos, fn($a, $b) => strcmp($b['nombre'], $a['nombre']));

    return $archivos;
  }

  /** Ruta de un backup existente, validando el nombre (evita leer otros archivos). */
  public function ruta(string $nombre): ?string
  {
    $ruta = $this->directorio . '/' . $nombre;

    return preg_match(self::PATRON, $nombre) && is_file($ruta) ? $ruta : null;
  }

  /**
   * Copia los backups recién creados a BACKUP_COPIA_DIR (si está configurada). Un fallo
   * se registra pero no invalida el backup local.
   *
   * @param list<string> $creados
   */
  private function copiarFueraDelServidor(array $creados, int $conservar): void
  {
    $destino = rtrim(trim(Env::get('BACKUP_COPIA_DIR', '')), '/');
    if ($destino === '') {
      return;
    }

    try {
      if (!is_dir($destino) || !is_writable($destino)) {
        throw new RuntimeException("La carpeta {$destino} no existe o no se puede escribir.");
      }
      foreach ($creados as $nombre) {
        if (!copy("{$this->directorio}/{$nombre}", "{$destino}/{$nombre}")) {
          throw new RuntimeException("No se pudo copiar {$nombre}.");
        }
      }
      $this->rotar($conservar, $destino);
    } catch (Throwable $e) {
      $this->logger->error('No se pudo copiar el backup a BACKUP_COPIA_DIR', ['exception' => $e]);
    }
  }

  /** Conserva los últimos $cantidad backups de cada tipo. Devuelve cuántos borró. */
  public function rotar(int $cantidad, ?string $directorio = null): int
  {
    $directorio ??= $this->directorio;
    $borrados = 0;
    foreach (['db', 'archivos'] as $tipo) {
      $lista = glob("{$directorio}/{$tipo}_*.gz") ?: [];
      rsort($lista);
      foreach (array_slice($lista, max(1, $cantidad)) as $viejo) {
        $borrados += (int) @unlink($viejo);
      }
    }

    return $borrados;
  }

  private function volcarBase(string $nombre): string
  {
    $ruta = "{$this->directorio}/{$nombre}";
    $gz = gzopen($ruta . '.tmp', 'wb6');
    if ($gz === false) {
      throw new RuntimeException('No se pudo crear el archivo de backup.');
    }

    // Todas las tablas se leen desde una misma foto de la base (lectura consistente de
    // InnoDB): lo que se escriba mientras corre el backup no lo deja a medias. Si ya hay
    // una transacción abierta, sus lecturas ya son consistentes.
    $snapshot = !$this->db->inTransaction();
    if ($snapshot) {
      $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
      $this->db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    }

    try {
      $escribir = fn(string $texto) => gzwrite($gz, $texto);
      $base = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();

      $escribir("-- Backup de {$base} generado el " . date('Y-m-d H:i:s') . "\n");
      $escribir("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n\n");

      $tablas = $this->db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
      foreach ($tablas as $tabla) {
        $crear = $this->db->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_NUM)[1];
        $escribir("DROP TABLE IF EXISTS `{$tabla}`;\n{$crear};\n");

        $stmt = $this->db->query("SELECT * FROM `{$tabla}`");
        $lote = [];
        $columnas = null;
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $columnas ??= '`' . implode('`, `', array_keys($fila)) . '`';
          $lote[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $this->db->quote((string) $v), $fila)) . ')';
          if (count($lote) >= self::FILAS_POR_INSERT) {
            $escribir("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n");
            $lote = [];
          }
        }
        if ($lote !== []) {
          $escribir("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n");
        }
        $escribir("\n");
      }

      $escribir("SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
    } catch (Throwable $e) {
      gzclose($gz);
      @unlink($ruta . '.tmp');
      throw $e;
    } finally {
      if ($snapshot) {
        $this->db->exec('COMMIT');
      }
    }

    gzclose($gz);
    rename($ruta . '.tmp', $ruta);

    return $nombre;
  }

  /** Imágenes subidas y configuración, con rutas relativas al proyecto (storage/...). */
  private function empaquetarArchivos(string $nombre): ?string
  {
    $carpetas = array_filter(['storage/uploads', 'storage/config'], fn($c) => is_dir("{$this->raiz}/{$c}"));
    if ($carpetas === []) {
      return null;
    }

    $tar = "{$this->directorio}/" . substr($nombre, 0, -3);
    @unlink($tar);
    $archivo = new PharData($tar);
    foreach ($carpetas as $carpeta) {
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$this->raiz}/{$carpeta}", \FilesystemIterator::SKIP_DOTS)) as $item) {
        if ($item->isFile() && !str_ends_with($item->getFilename(), '.tmp')) {
          $archivo->addFile($item->getPathname(), substr($item->getPathname(), strlen($this->raiz) + 1));
        }
      }
    }

    if ($archivo->count() === 0) {
      unset($archivo);
      @unlink($tar);

      return null;
    }

    $archivo->compress(\Phar::GZ);
    unset($archivo);
    @unlink($tar);

    return $nombre;
  }
}
