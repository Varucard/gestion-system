<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Repositories\Repository;
use App\Support\Validator;
use PDOException;

final class ProveedorService
{
  public function __construct(
    private readonly ProveedorRepository $proveedores,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->proveedores->find($id) ?? throw new NotFoundException('Proveedor no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function guardar(array $input, ?int $id = null): int
  {
    if ($id !== null) {
      $this->obtener($id);
    }

    $campo = fn(string $k) => Validator::nullable((string) ($input[$k] ?? ''));
    $nombre = trim((string) ($input['nombre'] ?? ''));
    $cuit = $campo('cuit');
    $email = $campo('email') !== null ? mb_strtolower($campo('email')) : null;

    (new Validator())
      ->check(Validator::largo($nombre, 2, 100), 'El nombre del proveedor debe tener entre 2 y 100 caracteres.')
      ->check($cuit === null || (bool) preg_match('/^\d{2}-?\d{8}-?\d$/', $cuit), 'El CUIT debe tener el formato 30-12345678-9.')
      ->check($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'El email no es válido.')
      ->validate();

    $proveedor = new Proveedor(
      $nombre, $cuit, $campo('contacto'), $campo('telefono'), $email, $campo('direccion'), $campo('observaciones'),
      $id === null || !empty($input['activo']), $id,
    );

    try {
      return $this->proveedores->save($proveedor);
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ya existe un proveedor con ese nombre.']) : $e;
    }
  }

  public function eliminar(int $id): void
  {
    $this->obtener($id);

    try {
      $proveedor = $this->obtener($id);
      $this->proveedores->delete($id);
      $this->auditor->registrar('eliminar', 'proveedor', $id, "Proveedor eliminado: {$proveedor['nombre']}");
    } catch (PDOException $e) {
      throw Repository::isReferenced($e)
        ? new ValidationException(['El proveedor tiene repuestos o ingresos de stock asociados. Podés desactivarlo.'])
        : $e;
    }
  }
}
