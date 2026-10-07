<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Support\Validator;

/**
 * Actualización masiva de precios (servicios y/o repuestos) por porcentaje,
 * con vista previa y redondeo.
 */
final class PrecioService
{
  public const APLICAR_A = ['servicios' => 'Servicios', 'repuestos' => 'Repuestos', 'ambos' => 'Servicios y repuestos'];
  public const REDONDEOS = [0 => 'Sin redondeo', 10 => 'A $10', 50 => 'A $50', 100 => 'A $100', 500 => 'A $500', 1000 => 'A $1.000'];
  /** Máximo que admite una columna decimal(10,2). */
  public const PRECIO_MAXIMO = Validator::IMPORTE_MAXIMO;

  public function __construct(
    private readonly ServicioRepository $servicios,
    private readonly RepuestoRepository $repuestos,
    private readonly Auditor $auditor,
  ) {
  }

  /** Precio de venta a partir del costo y un margen (%), redondeado a 2 decimales. */
  public static function conMargen(float $costo, float $margen): float
  {
    return round($costo * (1 + $margen / 100), 2);
  }

  /**
   * Aplica el porcentaje y redondea al múltiplo indicado (0 = sin redondeo): hacia arriba
   * en un aumento y hacia abajo en una rebaja, para que una rebaja nunca suba el precio.
   * Si el redondeo hacia abajo dejara el precio en 0, se conserva sin redondear.
   */
  public static function ajustar(float $precio, float $porcentaje, int $redondeo): float
  {
    $nuevo = round($precio * (1 + $porcentaje / 100), 2);
    if ($redondeo <= 0) {
      return $nuevo;
    }

    $redondeado = ($porcentaje < 0 ? floor($nuevo / $redondeo) : ceil($nuevo / $redondeo)) * $redondeo;

    return $redondeado > 0 ? (float) $redondeado : $nuevo;
  }

  /**
   * Calcula los precios nuevos sin guardar nada.
   *
   * @param array<string, mixed> $input aplicar_a, porcentaje, redondeo, proveedor_id, ids (opcional, selección)
   * @return array{parametros: array<string, mixed>, items: list<array<string, mixed>>}
   */
  public function vistaPrevia(array $input): array
  {
    $p = $this->parametros($input);
    $items = [];

    if ($p['aplicar_a'] !== 'repuestos') {
      foreach ($this->servicios->all() as $s) {
        $items[] = ['tipo' => 'servicio', 'id' => (int) $s['id'], 'nombre' => $s['nombre'], 'actual' => (float) $s['precio_base']];
      }
    }
    if ($p['aplicar_a'] !== 'servicios') {
      foreach ($this->repuestos->all() as $r) {
        if ($p['proveedor_id'] === null || (int) $r['proveedor_id'] === $p['proveedor_id']) {
          $items[] = ['tipo' => 'repuesto', 'id' => (int) $r['id'], 'nombre' => $r['nombre'], 'actual' => (float) $r['precio']];
        }
      }
    }

    foreach ($items as &$item) {
      $item['nuevo'] = self::ajustar($item['actual'], $p['porcentaje'], $p['redondeo']);
    }
    unset($item);

    $excedidos = array_filter($items, fn(array $i) => $i['nuevo'] > self::PRECIO_MAXIMO);
    (new Validator())
      ->check($excedidos === [], sprintf('Con ese porcentaje %s superaría el precio máximo admitido ($ %s).', $excedidos ? reset($excedidos)['nombre'] : '', money(self::PRECIO_MAXIMO)))
      ->validate();

    if ($p['ids'] !== null) {
      $items = array_values(array_filter($items, fn(array $i) => in_array("{$i['tipo']}:{$i['id']}", $p['ids'], true)));
    }

    return ['parametros' => $p, 'items' => $items];
  }

  /**
   * Aplica los precios de la vista previa, solo en los ítems seleccionados (sin selección no
   * se modifica nada). `actual[tipo:id]` trae el precio que se vio en la vista previa: si
   * alguno cambió desde entonces (otro usuario, o el mismo formulario enviado dos veces) no
   * se aplica nada, así el aumento nunca se suma dos veces.
   *
   * @return int cantidad de precios modificados
   */
  public function aplicar(array $input): int
  {
    $input['ids'] = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
    $vistos = isset($input['actual']) && is_array($input['actual']) ? $input['actual'] : [];

    ['parametros' => $p, 'items' => $items] = $this->vistaPrevia($input);
    $cambios = array_values(array_filter($items, fn(array $i) => $i['nuevo'] !== $i['actual']));

    (new Validator())->check($cambios !== [], 'No hay precios para modificar con esos criterios.')->validate();

    $cambiaron = 'Los precios cambiaron desde la vista previa (¿se aplicó dos veces?). Revisá los precios y generá la vista previa de nuevo.';
    foreach ($cambios as $c) {
      $visto = Validator::importe((string) ($vistos["{$c['tipo']}:{$c['id']}"] ?? ''));
      if ($visto === null || abs($visto - $c['actual']) > 0.001) {
        throw new ValidationException([$cambiaron]);
      }
    }

    $this->servicios->transaction(function () use ($cambios, $cambiaron) {
      foreach ($cambios as $c) {
        $ok = $c['tipo'] === 'servicio'
          ? $this->servicios->setPrecio($c['id'], $c['nuevo'], $c['actual'])
          : $this->repuestos->setPrecio($c['id'], $c['nuevo'], $c['actual']);
        if (!$ok) {
          throw new ValidationException([$cambiaron]);
        }
      }
    });

    $this->auditor->registrar(
      'aumento_masivo',
      'precios',
      null,
      sprintf('Actualización masiva: %s%% sobre %d precios (%s, %s)', ($p['porcentaje'] > 0 ? '+' : '') . qty($p['porcentaje']), count($cambios), self::APLICAR_A[$p['aplicar_a']], self::REDONDEOS[$p['redondeo']]),
      ['cambios' => array_map(fn(array $c) => ['tipo' => $c['tipo'], 'id' => $c['id'], 'antes' => $c['actual'], 'despues' => $c['nuevo']], $cambios)],
    );

    return count($cambios);
  }

  /** @return array{aplicar_a: string, porcentaje: float, redondeo: int, proveedor_id: ?int, ids: ?list<string>} */
  private function parametros(array $input): array
  {
    $aplicarA = (string) ($input['aplicar_a'] ?? '');
    $porcentaje = str_replace(',', '.', trim((string) ($input['porcentaje'] ?? '')));
    $redondeo = (int) ($input['redondeo'] ?? 0);
    $ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_map('strval', $input['ids'])) : null;

    (new Validator())
      ->check(isset(self::APLICAR_A[$aplicarA]), 'Elegí a qué precios aplicar el cambio.')
      ->check(is_numeric($porcentaje) && (float) $porcentaje >= -90 && (float) $porcentaje <= 500 && (float) $porcentaje != 0, 'El porcentaje debe estar entre -90 y 500 (distinto de 0).')
      ->check(isset(self::REDONDEOS[$redondeo]), 'Redondeo inválido.')
      ->validate();

    return [
      'aplicar_a' => $aplicarA,
      'porcentaje' => round((float) $porcentaje, 2),
      'redondeo' => $redondeo,
      'proveedor_id' => (int) ($input['proveedor_id'] ?? 0) ?: null,
      'ids' => $ids,
    ];
  }
}
