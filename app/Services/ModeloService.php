<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Modelo;
use App\Repositories\MarcaRepository;
use App\Repositories\ModeloRepository;
use App\Repositories\Repository;
use App\Support\Validator;
use PDOException;

final class ModeloService
{
  public function __construct(
    private readonly ModeloRepository $modelos,
    private readonly MarcaRepository $marcas,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->modelos->find($id) ?? throw new NotFoundException('Modelo no encontrado.');
  }

  public function guardar(int $marcaId, string $nombre, ?int $id = null): int
  {
    if ($id !== null) {
      $this->obtener($id);
    }

    $nombre = preg_replace('/\s+/u', ' ', trim($nombre));
    (new Validator())
      ->check($marcaId > 0 && $this->marcas->find($marcaId) !== null, 'Seleccioná una marca válida.')
      ->check((bool) preg_match('/^[\p{L}\d\s.\/-]{1,50}$/u', $nombre), 'El nombre del modelo debe tener entre 1 y 50 caracteres alfanuméricos.')
      ->validate();

    try {
      return $this->modelos->save(new Modelo($marcaId, $nombre, $id));
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e) ? new ValidationException(['Ese modelo ya existe para la marca seleccionada.']) : $e;
    }
  }

  /**
   * Modelo escrito al cargar un equipo: si la marca ya lo tiene (sin importar mayúsculas)
   * se usa ese; si no, se agrega al catálogo. Así no hace falta ir a Configuración > Modelos.
   */
  public function obtenerOCrear(int $marcaId, string $nombre): int
  {
    $nombre = preg_replace('/\s+/u', ' ', trim($nombre));
    $existente = $marcaId > 0 ? $this->modelos->porNombre($marcaId, $nombre) : null;
    if ($existente !== null) {
      return (int) $existente['id'];
    }

    $id = $this->guardar($marcaId, $nombre);
    $this->auditor->registrar('crear', 'modelo', $id, "Modelo agregado al cargar un equipo: {$nombre}");

    return $id;
  }

  public function eliminar(int $id): void
  {
    $this->obtener($id);

    try {
      $modelo = $this->obtener($id);
      $this->modelos->delete($id);
      $this->auditor->registrar('eliminar', 'modelo', $id, "Modelo eliminado: {$modelo['nombre']}");
    } catch (PDOException $e) {
      throw Repository::isReferenced($e)
        ? new ValidationException(['El modelo tiene equipos asociados y no se puede eliminar.'])
        : $e;
    }
  }
}
