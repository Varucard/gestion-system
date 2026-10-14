<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Estado;
use App\Enums\TipoEquipo;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Equipo;
use App\Repositories\ClienteRepository;
use App\Repositories\ModeloRepository;
use App\Repositories\Repository;
use App\Repositories\EquipoRepository;
use App\Support\ImageUpload;
use App\Support\Validator;
use PDOException;

final class EquipoService
{
  public function __construct(
    private readonly EquipoRepository $equipos,
    private readonly ClienteRepository $clientes,
    private readonly ModeloRepository $modelos,
    private readonly Auditor $auditor,
    private readonly ModeloService $modeloService,
    private readonly MarcaService $marcaService,
  ) {
  }

  /** Prefijo del modelo escrito a mano en el formulario (no está en el catálogo todavía). */
  public const MODELO_NUEVO = 'nuevo:';

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->equipos->find($id) ?? throw new NotFoundException('Equipo no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function crear(array $input): int
  {
    // Si el modelo es nuevo y algo del equipo no valida, el modelo tampoco queda creado.
    return $this->equipos->transaction(function () use ($input) {
      $equipo = $this->construir($this->conModelo($input));
      $id = $this->persistir($equipo);
      $this->auditor->registrar('crear', 'equipo', $id, "Equipo registrado: " . self::describir($equipo));

      return $id;
    });
  }

  /** Normaliza un número de serie como se guarda: mayúsculas y sin espacios. */
  public static function normalizarSerie(string $serie): string
  {
    return strtoupper(preg_replace('/\s/', '', $serie));
  }

  /** De 3 a 50 letras, números, guiones, puntos o barras (ya normalizado). */
  public static function serieValida(string $serie): bool
  {
    return (bool) preg_match('/^[A-Z0-9\/._-]{3,50}$/', $serie);
  }

  /**
   * Resuelve la marca y el modelo escritos a mano ("nuevo:Lenovo", "nuevo:IdeaPad 3"): los busca
   * en el catálogo o los agrega, y deja sus ids en marca_id y modelo_id. Va dentro de la
   * transacción de crear()/actualizar(): si el equipo no valida, tampoco quedan creados.
   *
   * @param array<string, mixed> $input
   * @return array<string, mixed>
   */
  private function conModelo(array $input): array
  {
    $marca = (string) ($input['marca_id'] ?? '');
    if (str_starts_with($marca, self::MODELO_NUEVO)) {
      $input['marca_id'] = $this->marcaService->obtenerOCrear(substr($marca, strlen(self::MODELO_NUEVO)));
    }

    $modelo = (string) ($input['modelo_id'] ?? '');
    if (!str_starts_with($modelo, self::MODELO_NUEVO)) {
      return $input;
    }

    $input['modelo_id'] = $this->modeloService->obtenerOCrear((int) ($input['marca_id'] ?? 0), substr($modelo, strlen(self::MODELO_NUEVO)));

    return $input;
  }

  /** @param array<string, mixed> $input */
  public function actualizar(int $id, array $input): void
  {
    $this->obtener($id);
    $this->equipos->transaction(function () use ($id, $input) {
      $equipo = $this->construir($this->conModelo($input), $id);
      $this->persistir($equipo);
      $this->auditor->registrar('editar', 'equipo', $id, "Equipo editado: " . self::describir($equipo));
    });
  }

  /** @param array<string, mixed> $archivo elemento de $_FILES */
  /** @param int|null $ordenId la orden en la que se sacó (fotos de la recepción) */
  public function agregarImagen(int $id, array $archivo, ?string $descripcion, ?int $usuarioId, ?int $ordenId = null): void
  {
    $this->obtener($id);
    $descripcion = Validator::nullable((string) $descripcion);
    (new Validator())->check($descripcion === null || Validator::largo($descripcion, 1, 255), 'La descripción es demasiado larga.')->validate();

    $nombre = ImageUpload::en('equipos')->guardar($archivo);
    $this->equipos->agregarImagen($id, $nombre, $descripcion, $usuarioId, $ordenId);
  }

  /** Elimina una imagen. Devuelve el id del equipo. */
  public function eliminarImagen(int $imagenId): int
  {
    $imagen = $this->equipos->imagen($imagenId) ?? throw new NotFoundException('Imagen no encontrada.');
    $this->equipos->eliminarImagen($imagenId);
    ImageUpload::en('equipos')->eliminar($imagen['archivo']);

    return (int) $imagen['equipo_id'];
  }

  public function rutaImagen(int $equipoId, int $imagenId): string
  {
    $imagen = $this->equipos->imagen($imagenId);

    return ($imagen && (int) $imagen['equipo_id'] === $equipoId ? ImageUpload::en('equipos')->ruta($imagen['archivo']) : null)
      ?? throw new NotFoundException('Imagen no encontrada.');
  }

  public function alternarEstado(int $id): Estado
  {
    $nuevo = Estado::from($this->obtener($id)['estado'])->alternar();
    $this->equipos->setEstado($id, $nuevo);
    $this->auditor->registrar('cambiar_estado', 'equipo', $id, "Equipo #{$id} pasó a {$nuevo->value}");

    return $nuevo;
  }

  private function persistir(Equipo $equipo): int
  {
    try {
      if ($equipo->id === null) {
        return $this->equipos->create($equipo);
      }
      $this->equipos->update($equipo);

      return $equipo->id;
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e)
        ? new ValidationException(
          ['El número de serie ya corresponde a otro equipo registrado.'],
          ['numero_serie' => 'Ese número de serie ya es de otro equipo.'],
        )
        : $e;
    }
  }

  /** Texto corto para la auditoría: tipo y, si lo tiene, número de serie. */
  private static function describir(Equipo $equipo): string
  {
    return $equipo->tipo->label() . ($equipo->numeroSerie !== null ? " S/N {$equipo->numeroSerie}" : '');
  }

  /** @param array<string, mixed> $input */
  private function construir(array $input, ?int $id = null): Equipo
  {
    $clienteId = (int) ($input['cliente_id'] ?? 0);
    $tipo = TipoEquipo::tryFrom(trim((string) ($input['tipo'] ?? '')));
    $marcaId = (int) ($input['marca_id'] ?? 0);
    $modeloId = (int) ($input['modelo_id'] ?? 0);
    $serie = Validator::nullable(self::normalizarSerie((string) ($input['numero_serie'] ?? '')));
    $procesador = Validator::nullable((string) ($input['procesador'] ?? ''));
    $memoria = Validator::nullable((string) ($input['memoria'] ?? ''));
    $almacenamiento = Validator::nullable((string) ($input['almacenamiento'] ?? ''));
    $color = Validator::nullable((string) ($input['color'] ?? ''));

    (new Validator())
      ->check($clienteId > 0 && $this->clientes->find($clienteId) !== null, 'Seleccioná un cliente válido.', 'cliente_id')
      ->check($tipo !== null, 'Seleccioná el tipo de equipo.', 'tipo')
      ->check($marcaId > 0 && $modeloId > 0 && $this->modelos->perteneceAMarca($modeloId, $marcaId), 'Seleccioná una marca y un modelo válidos.', 'modelo_id')
      ->check($serie === null || self::serieValida($serie), 'El número de serie debe tener entre 3 y 50 caracteres (letras, números, guiones, puntos o barras).', 'numero_serie')
      ->check($procesador === null || Validator::largo($procesador, 1, 80), 'El procesador no puede superar los 80 caracteres.', 'procesador')
      ->check($memoria === null || Validator::largo($memoria, 1, 30), 'La memoria no puede superar los 30 caracteres.', 'memoria')
      ->check($almacenamiento === null || Validator::largo($almacenamiento, 1, 50), 'El almacenamiento no puede superar los 50 caracteres.', 'almacenamiento')
      ->check($color === null || Validator::largo($color, 1, 30), 'El color no puede superar los 30 caracteres.', 'color')
      ->validate();

    return new Equipo(
      $clienteId, $tipo, $marcaId, $modeloId, $serie, id: $id,
      procesador: $procesador, memoria: $memoria, almacenamiento: $almacenamiento, color: $color,
      detalle: Validator::nullable((string) ($input['detalle'] ?? '')),
    );
  }
}
