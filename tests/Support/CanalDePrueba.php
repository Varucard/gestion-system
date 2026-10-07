<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Notificaciones\CanalNotificacion;
use App\Notificaciones\Destinatario;
use App\Notificaciones\Mensaje;
use RuntimeException;

/** Canal que guarda los mensajes en memoria en lugar de enviarlos. */
final class CanalDePrueba implements CanalNotificacion
{
  /** @var list<array{destinatario: Destinatario, mensaje: Mensaje}> */
  public array $enviados = [];

  public bool $fallar = false;

  public function __construct(private readonly string $nombre = 'email')
  {
  }

  public function nombre(): string
  {
    return $this->nombre;
  }

  public function disponible(): bool
  {
    return true;
  }

  public function puedeEnviarA(Destinatario $destinatario): bool
  {
    return $destinatario->email !== null;
  }

  public function destino(Destinatario $destinatario): string
  {
    return (string) $destinatario->email;
  }

  public function enviar(Destinatario $destinatario, Mensaje $mensaje): void
  {
    if ($this->fallar) {
      throw new RuntimeException('Servidor caído');
    }
    $this->enviados[] = ['destinatario' => $destinatario, 'mensaje' => $mensaje];
  }
}
