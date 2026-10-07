<?php

declare(strict_types=1);

namespace App\Repositories;

/** Búsqueda rápida global por n° de serie, marca/modelo, DNI, apellido/nombre o número de orden. */
final class BusquedaRepository extends Repository
{
  private const LIMITE = 20;

  /** @return array{clientes: list<array<string, mixed>>, equipos: list<array<string, mixed>>, ordenes: list<array<string, mixed>>} */
  public function buscar(string $texto): array
  {
    $texto = trim($texto);
    $limite = self::LIMITE;
    $soloDigitos = preg_replace('/[.\s-]/', '', ltrim($texto, '#'));
    $serie = strtoupper(preg_replace('/\s/', '', $texto));
    $like = '%' . addcslashes($texto, '%_\\') . '%';

    $clientes = $this->fetchAll(
      "SELECT c.id, p.nombre, p.apellido, p.dni, c.telefono, c.estado
         FROM clientes c INNER JOIN personas p ON p.id = c.persona_id
        WHERE p.dni LIKE ? OR CONCAT(p.apellido, ' ', p.nombre) LIKE ? OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?
        ORDER BY p.apellido, p.nombre LIMIT {$limite}",
      [ctype_digit($soloDigitos) ? "{$soloDigitos}%" : '-', $like, $like]
    );

    $equipos = $this->fetchAll(
      "SELECT eq.id, eq.tipo, eq.numero_serie, eq.estado, ma.nombre AS marca, mo.nombre AS modelo,
              CONCAT(p.apellido, ', ', p.nombre) AS cliente
         FROM equipos eq
         INNER JOIN marcas ma ON ma.id = eq.marca_id
         INNER JOIN modelos mo ON mo.id = eq.modelo_id
         INNER JOIN clientes c ON c.id = eq.cliente_id
         INNER JOIN personas p ON p.id = c.persona_id
        WHERE eq.numero_serie LIKE ? OR CONCAT(ma.nombre, ' ', mo.nombre) LIKE ?
        ORDER BY ma.nombre, mo.nombre LIMIT {$limite}",
      ['%' . addcslashes($serie, '%_\\') . '%', $like]
    );

    $ordenes = ctype_digit($soloDigitos) && strlen($soloDigitos) <= 9
      ? $this->fetchAll(
        "SELECT o.id, o.estado, o.total, o.created_at, eq.tipo, eq.numero_serie, ma.nombre AS marca, mo.nombre AS modelo,
                CONCAT(p.apellido, ', ', p.nombre) AS cliente
           FROM ordenes o
           INNER JOIN equipos eq ON eq.id = o.equipo_id
           INNER JOIN marcas ma ON ma.id = eq.marca_id
           INNER JOIN modelos mo ON mo.id = eq.modelo_id
           INNER JOIN clientes c ON c.id = o.cliente_id
           INNER JOIN personas p ON p.id = c.persona_id
          WHERE o.id = ?",
        [(int) $soloDigitos]
      )
      : [];

    return ['clientes' => $clientes, 'equipos' => $equipos, 'ordenes' => $ordenes];
  }
}
