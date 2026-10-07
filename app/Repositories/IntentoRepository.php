<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Registro de intentos fallidos para limitar abusos (login, portal de seguimiento).
 */
final class IntentoRepository extends Repository
{
  public function registrar(string $ambito, string $clave, string $ip): void
  {
    $this->execute('INSERT INTO intentos (ambito, clave, ip) VALUES (?, ?, ?)', [$ambito, $clave, $ip]);
  }

  /**
   * ¿Corresponde bloquear? Se cuentan los intentos fallidos recientes de tres formas:
   *  - misma clave (usuario o DNI) desde la misma IP: el límite normal;
   *  - desde la misma IP con cualquier clave: alguien probando muchas cuentas;
   *  - misma clave desde cualquier IP: un ataque repartido entre muchas IPs (límite más alto).
   * Así, alguien desde otra IP no puede dejar bloqueado a un usuario con unos pocos intentos.
   */
  public function bloqueado(string $ambito, string $clave, string $ip, int $minutos, int $porClaveEIp, int $porIp, int $porClave): bool
  {
    $fila = $this->fetchOne(
      'SELECT COALESCE(SUM(clave = ? AND ip = ?), 0) AS clave_ip, COALESCE(SUM(ip = ?), 0) AS ip, COALESCE(SUM(clave = ?), 0) AS clave
         FROM intentos
        WHERE ambito = ? AND (clave = ? OR ip = ?) AND created_at > NOW() - INTERVAL ? MINUTE',
      [$clave, $ip, $ip, $clave, $ambito, $clave, $ip, $minutos]
    );

    return (int) $fila['clave_ip'] >= $porClaveEIp || (int) $fila['ip'] >= $porIp || (int) $fila['clave'] >= $porClave;
  }

  public function limpiar(string $ambito, string $clave): void
  {
    $this->execute('DELETE FROM intentos WHERE ambito = ? AND clave = ?', [$ambito, $clave]);
  }

  /** Borra registros viejos (tarea periódica). */
  public function purgar(): int
  {
    return $this->execute('DELETE FROM intentos WHERE created_at < NOW() - INTERVAL 1 DAY');
  }
}
