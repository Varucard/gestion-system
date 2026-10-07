<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\ComboRepository;
use App\Repositories\Repository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Support\Validator;
use PDOException;

/** Combos: conjuntos de servicios y repuestos que se agregan juntos a una orden. */
final class ComboService
{
  public function __construct(
    private readonly ComboRepository $combos,
    private readonly ServicioRepository $servicios,
    private readonly RepuestoRepository $repuestos,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->combos->find($id) ?? throw new NotFoundException('Combo no encontrado.');
  }

  /**
   * @param array<string, mixed> $input nombre, descripcion, activo, items: ['servicio' => [id => cantidad], 'repuesto' => [...]]
   */
  public function guardar(array $input, ?int $id = null): int
  {
    if ($id !== null) {
      $this->obtener($id);
    }

    $nombre = trim((string) ($input['nombre'] ?? ''));
    $items = ['servicio' => [], 'repuesto' => []];
    $cantidadesValidas = true;

    foreach (['servicio', 'repuesto'] as $tipo) {
      foreach ((array) ($input['items'][$tipo] ?? []) as $itemId => $cantidad) {
        $valor = Validator::importe((string) $cantidad);
        $cantidadesValidas = $cantidadesValidas && $valor !== null && $valor > 0;
        $items[$tipo][(int) $itemId] = (float) $valor;
      }
    }

    (new Validator())
      ->check(Validator::largo($nombre, 2, 100), 'El nombre del combo debe tener entre 2 y 100 caracteres.')
      ->check($items['servicio'] !== [] || $items['repuesto'] !== [], 'Agregá al menos un servicio o repuesto al combo.')
      ->check($cantidadesValidas, 'Las cantidades deben ser mayores a 0.')
      ->check(count($this->servicios->precios(array_keys($items['servicio']))) === count($items['servicio']), 'Alguno de los servicios no existe.')
      ->check(count($this->repuestos->precios(array_keys($items['repuesto']))) === count($items['repuesto']), 'Alguno de los repuestos no existe.')
      ->validate();

    try {
      $guardado = $this->combos->save($id, $nombre, Validator::nullable((string) ($input['descripcion'] ?? '')), $id === null || !empty($input['activo']), $items);
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ya existe un combo con ese nombre.']) : $e;
    }

    $this->auditor->registrar($id === null ? 'crear' : 'editar', 'combo', $guardado, ($id === null ? 'Combo creado: ' : 'Combo editado: ') . $nombre);

    return $guardado;
  }

  public function eliminar(int $id): void
  {
    $combo = $this->obtener($id);
    $this->combos->delete($id);
    $this->auditor->registrar('eliminar', 'combo', $id, "Combo eliminado: {$combo['nombre']}");
  }
}
