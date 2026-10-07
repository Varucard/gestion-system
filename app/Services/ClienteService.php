<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Estado;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use App\Repositories\Repository;
use App\Support\ImageUpload;
use App\Support\Validator;
use PDOException;

final class ClienteService
{
  public function __construct(
    private readonly ClienteRepository $clientes,
    private readonly Auditor $auditor,
  ) {
  }

  /** @return array<string, mixed> */
  public function obtener(int $id): array
  {
    return $this->clientes->find($id) ?? throw new NotFoundException('Cliente no encontrado.');
  }

  /** @param array<string, mixed> $input */
  public function crear(array $input): int
  {
    $cliente = $this->construir($input);

    try {
      $id = $this->clientes->create($cliente);
      $this->auditor->registrar('crear', 'cliente', $id, "Cliente creado: {$cliente->nombreCompleto()} (DNI {$cliente->dni})");

      return $id;
    } catch (PDOException $e) {
      throw Repository::isDuplicate($e)
        ? new ValidationException(['Ya existe un cliente con ese DNI.'])
        : $e;
    }
  }

  /** @param array<string, mixed> $input */
  public function actualizar(int $id, array $input): void
  {
    $actual = $this->obtener($id);

    // El DNI es la identidad de la persona: no se modifica al editar.
    $input['dni'] = $actual['dni'];
    $cliente = $this->construir($input, $id);
    $this->clientes->update($cliente);
    $this->auditor->registrar('editar', 'cliente', $id, "Cliente editado: {$cliente->nombreCompleto()}");
  }

  /** @param array<string, mixed> $archivo elemento de $_FILES */
  public function cambiarFoto(int $id, array $archivo): void
  {
    $anterior = $this->obtener($id)['foto'];
    $uploads = ImageUpload::en('clientes');

    $this->clientes->setFoto($id, $uploads->guardar($archivo));
    $uploads->eliminar($anterior);
  }

  public function quitarFoto(int $id): void
  {
    $anterior = $this->obtener($id)['foto'];
    $this->clientes->setFoto($id, null);
    ImageUpload::en('clientes')->eliminar($anterior);
  }

  public function rutaFoto(int $id): string
  {
    $foto = $this->obtener($id)['foto'];

    return ($foto ? ImageUpload::en('clientes')->ruta($foto) : null)
      ?? throw new NotFoundException('El cliente no tiene foto.');
  }

  public function alternarEstado(int $id): Estado
  {
    $nuevo = Estado::from($this->obtener($id)['estado'])->alternar();
    $this->clientes->setEstado($id, $nuevo);
    $this->auditor->registrar('cambiar_estado', 'cliente', $id, "Cliente #{$id} pasó a {$nuevo->value}");

    return $nuevo;
  }

  public function eliminar(int $id): void
  {
    $this->obtener($id);

    if ($this->clientes->tieneHistorial($id)) {
      throw new ValidationException([
        'El cliente tiene equipos o turnos registrados y no se puede eliminar. Podés desactivarlo.',
      ]);
    }

    $cliente = $this->obtener($id);
    $this->clientes->delete($id);
    $this->auditor->registrar('eliminar', 'cliente', $id, "Cliente eliminado: {$cliente['apellido']}, {$cliente['nombre']} (DNI {$cliente['dni']})");
  }

  /** @param array<string, mixed> $input */
  private function construir(array $input, ?int $id = null): Cliente
  {
    $nombre = mb_strtoupper(trim((string) ($input['nombre'] ?? '')));
    $apellido = mb_strtoupper(trim((string) ($input['apellido'] ?? '')));
    $dni = trim((string) ($input['dni'] ?? ''));
    $telefono = preg_replace('/\D/', '', (string) ($input['telefono'] ?? ''));
    $direccion = Validator::nullable(mb_strtoupper((string) ($input['direccion'] ?? '')));
    $email = Validator::nullable(mb_strtolower((string) ($input['email'] ?? '')));

    (new Validator())
      ->check(Validator::soloLetras($nombre, 2, 50), 'El nombre debe contener solo letras (2 a 50 caracteres).')
      ->check(Validator::soloLetras($apellido, 2, 50), 'El apellido debe contener solo letras (2 a 50 caracteres).')
      ->check((bool) preg_match('/^\d{6,8}$/', $dni), 'El DNI debe tener entre 6 y 8 dígitos.')
      ->check((bool) preg_match('/^\d{10}$/', $telefono), 'El teléfono debe tener 10 dígitos (código de área + número).')
      ->check($direccion === null || Validator::largo($direccion, 5, 200), 'La dirección debe tener entre 5 y 200 caracteres.')
      ->check($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'El email no tiene un formato válido.')
      ->validate();

    return new Cliente($nombre, $apellido, $dni, $telefono, $direccion, $email, id: $id);
  }
}
