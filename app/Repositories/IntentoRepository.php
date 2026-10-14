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
    $this->execute('INSERT INTO intentos (ambito, clave, ip) VALUES (?, ?, ?)', [$ambito, $clave, self::red($ip)]);
  }

  /**
   * Anota el intento y dice si con él se pasa algún límite (ver bloqueado()). Se anota ANTES de
   * verificar la clave, y quien acierta lo borra con limpiar(): así, muchos pedidos a la vez no
   * pasan todos el control antes de que se registre el primer error. Cada pedido ve, como
   * mínimo, su propio intento y los anteriores, así que pasan como mucho los permitidos.
   */
  public function anotarYVerificar(string $ambito, string $clave, string $ip, int $minutos, int $porClaveEIp, int $porIp, int $porClave): bool
  {
    $this->registrar($ambito, $clave, $ip);

    return $this->bloqueado($ambito, $clave, $ip, $minutos, $porClaveEIp + 1, $porIp + 1, $porClave + 1);
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
    $ip = self::red($ip);
    $fila = $this->fetchOne(
      'SELECT COALESCE(SUM(clave = ? AND ip = ?), 0) AS clave_ip, COALESCE(SUM(ip = ?), 0) AS ip, COALESCE(SUM(clave = ?), 0) AS clave
         FROM intentos
        WHERE ambito = ? AND (clave = ? OR ip = ?) AND created_at > NOW() - INTERVAL ? MINUTE',
      [$clave, $ip, $ip, $clave, $ambito, $clave, $ip, $minutos]
    );

    return (int) $fila['clave_ip'] >= $porClaveEIp || (int) $fila['ip'] >= $porIp || (int) $fila['clave'] >= $porClave;
  }

  /** Intentos fallidos recientes de esa clave desde esa IP. */
  public function contar(string $ambito, string $clave, string $ip, int $minutos): int
  {
    return (int) $this->fetchOne(
      'SELECT COUNT(*) AS n FROM intentos WHERE ambito = ? AND clave = ? AND ip = ? AND created_at > NOW() - INTERVAL ? MINUTE',
      [$ambito, $clave, self::red($ip), $minutos]
    )['n'];
  }

  public function limpiar(string $ambito, string $clave): void
  {
    $this->execute('DELETE FROM intentos WHERE ambito = ? AND clave = ?', [$ambito, $clave]);
  }

  /**
   * Con IPv6, una sola conexión suele tener millones de direcciones (todo un /64): se cuentan
   * como una. Las IPv4 quedan tal cual.
   */
  public static function red(string $ip): string
  {
    $binaria = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? inet_pton($ip) : false;
    if ($binaria === false || strlen($binaria) !== 16) {
      return $ip;
    }
    // IPv4 escrita como IPv6 (::ffff:1.2.3.4): es una IPv4.
    if (str_starts_with($binaria, str_repeat("\0", 10) . "\xff\xff")) {
      return (string) inet_ntop(substr($binaria, 12));
    }

    return inet_ntop(substr($binaria, 0, 8) . str_repeat("\0", 8)) . '/64';
  }

  /** Borra registros viejos (tarea periódica). */
  public function purgar(): int
  {
    return $this->execute('DELETE FROM intentos WHERE created_at < NOW() - INTERVAL 1 DAY');
  }
}
