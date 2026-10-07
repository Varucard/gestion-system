<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Marca;
use App\Repositories\MarcaRepository;
use App\Repositories\Repository;
use App\Support\Validator;
use PDOException;

final class MarcaService
{
  public function __construct(
    private readonly MarcaRepository $marcas,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->marcas->find($id) ?? throw new NotFoundException('Marca no encontrada.');
  }

  public function guardar(string $nombre, ?int $id = null): int
  {
    if ($id !== null) {
      $this->obtener($id);
    }

    $nombre = preg_replace('/\s+/u', ' ', trim($nombre));
    (new Validator())
      ->check((bool) preg_match('/^[\p{L}\d\s.&-]{2,50}$/u', $nombre), 'El nombre de la marca debe tener entre 2 y 50 caracteres.')
      ->validate();

    try {
      return $this->marcas->save(new Marca($nombre, $id));
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ya existe una marca con ese nombre.']) : $e;
    }
  }

  public function eliminar(int $id): void
  {
    $this->obtener($id);

    try {
      $marca = $this->obtener($id);
      $this->marcas->delete($id);
      $this->auditor->registrar('eliminar', 'marca', $id, "Marca eliminada: {$marca['nombre']}");
    } catch (PDOException $e) {
      throw Repository::isReferenced($e)
        ? new ValidationException(['La marca tiene modelos o equipos asociados y no se puede eliminar.'])
        : $e;
    }
  }
}
