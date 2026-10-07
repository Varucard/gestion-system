<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Repuesto;
use App\Repositories\ProveedorRepository;
use App\Repositories\Repository;
use App\Repositories\RepuestoRepository;
use App\Support\Validator;
use PDOException;

final class RepuestoService
{
  public function __construct(
    private readonly RepuestoRepository $repuestos,
    private readonly ProveedorRepository $proveedores,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->repuestos->find($id) ?? throw new NotFoundException('Repuesto no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function guardar(array $input, ?int $id = null): int
  {
    $anterior = $id !== null ? $this->obtener($id) : null;

    $nombre = trim((string) ($input['nombre'] ?? ''));
    $codigo = Validator::nullable(mb_strtoupper((string) ($input['codigo'] ?? '')));
    $precio = Validator::importe((string) ($input['precio'] ?? ''));
    $costoTexto = trim((string) ($input['precio_costo'] ?? ''));
    $costo = $costoTexto === '' ? null : Validator::importe($costoTexto);
    $minimo = Validator::importe((string) ($input['stock_minimo'] ?? '0') ?: '0');
    $proveedorId = (int) ($input['proveedor_id'] ?? 0) ?: null;
    $descripcion = Validator::nullable((string) ($input['descripcion'] ?? ''));

    (new Validator())
      ->check(Validator::largo($nombre, 2, 150), 'El nombre del repuesto debe tener entre 2 y 150 caracteres.')
      ->check($codigo === null || Validator::largo($codigo, 1, 50), 'El código no puede superar los 50 caracteres.')
      ->check($precio !== null, 'El precio debe ser un número mayor o igual a 0.')
      ->check($costoTexto === '' || $costo !== null, 'El precio de costo debe ser un número mayor o igual a 0.')
      ->check($minimo !== null, 'El stock mínimo debe ser un número mayor o igual a 0.')
      ->check($proveedorId === null || $this->proveedores->find($proveedorId) !== null, 'El proveedor seleccionado no existe.')
      ->validate();

    try {
      $guardado = $this->repuestos->save(new Repuesto($nombre, $precio, $descripcion, $codigo, $minimo, $proveedorId, $id, $costo));
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ya existe un repuesto con ese código.']) : $e;
    }

    if ($anterior === null) {
      $this->auditor->registrar('crear', 'repuesto', $guardado, "Repuesto creado: {$nombre} ($ " . money($precio) . ')');
    } elseif ((float) $anterior['precio'] !== $precio) {
      $this->auditor->registrar('cambiar_precio', 'repuesto', $guardado, "Precio de \"{$nombre}\": $ " . money($anterior['precio']) . ' → $ ' . money($precio), ['antes' => (float) $anterior['precio'], 'despues' => $precio]);
    }

    return $guardado;
  }

  public function eliminar(int $id): void
  {
    $repuesto = $this->obtener($id);

    try {
      $this->repuestos->delete($id);
      $this->auditor->registrar('eliminar', 'repuesto', $id, "Repuesto eliminado: {$repuesto['nombre']}");
    } catch (PDOException $e) {
      throw Repository::isReferenced($e)
        ? new ValidationException(['El repuesto tiene órdenes, movimientos de stock o es parte de un combo y no se puede eliminar (si está en un combo, quitalo del combo primero).'])
        : $e;
    }
  }
}
