<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Servicio;
use App\Repositories\Repository;
use App\Repositories\ServicioRepository;
use App\Support\Validator;
use PDOException;

final class ServicioService
{
  public function __construct(
    private readonly ServicioRepository $servicios,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->servicios->find($id) ?? throw new NotFoundException('Servicio no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function guardar(array $input, ?int $id = null): int
  {
    $anterior = $id !== null ? $this->obtener($id) : null;

    $nombre = trim((string) ($input['nombre'] ?? ''));
    $precio = Validator::importe((string) ($input['precio_base'] ?? ''));
    $descripcion = Validator::nullable((string) ($input['descripcion'] ?? ''));

    (new Validator())
      ->check(Validator::largo($nombre, 2, 100), 'El nombre del servicio debe tener entre 2 y 100 caracteres.')
      ->check($precio !== null, 'El precio base debe ser un número mayor o igual a 0.')
      ->validate();

    try {
      $guardado = $this->servicios->save(new Servicio($nombre, $precio, $descripcion, $id));
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ya existe un servicio con ese nombre.']) : $e;
    }

    if ($anterior === null) {
      $this->auditor->registrar('crear', 'servicio', $guardado, "Servicio creado: {$nombre} ($ " . money($precio) . ')');
    } elseif ((float) $anterior['precio_base'] !== $precio) {
      $this->auditor->registrar('cambiar_precio', 'servicio', $guardado, "Precio de \"{$nombre}\": $ " . money($anterior['precio_base']) . ' → $ ' . money($precio), ['antes' => (float) $anterior['precio_base'], 'despues' => $precio]);
    }

    return $guardado;
  }

  public function eliminar(int $id): void
  {
    $servicio = $this->obtener($id);

    try {
      $this->servicios->delete($id);
      $this->auditor->registrar('eliminar', 'servicio', $id, "Servicio eliminado: {$servicio['nombre']}");
    } catch (PDOException $e) {
      throw Repository::isReferenced($e)
        ? new ValidationException(['El servicio figura en órdenes o en combos y no se puede eliminar (si es parte de un combo, quitalo del combo primero).'])
        : $e;
    }
  }
}
