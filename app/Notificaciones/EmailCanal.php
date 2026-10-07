<?php

declare(strict_types=1);

namespace App\Notificaciones;

use App\Core\Env;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Envío por SMTP (Symfony Mailer). Requiere MAIL_DSN. */
final class EmailCanal implements CanalNotificacion
{
  /** @param array<string, string> $negocio sección "negocio" de la configuración */
  public function __construct(private readonly array $negocio)
  {
  }

  public function nombre(): string
  {
    return 'email';
  }

  public function disponible(): bool
  {
    return Env::get('MAIL_DSN') !== null;
  }

  public function puedeEnviarA(Destinatario $destinatario): bool
  {
    return $destinatario->email !== null && filter_var($destinatario->email, FILTER_VALIDATE_EMAIL) !== false;
  }

  public function destino(Destinatario $destinatario): string
  {
    return (string) $destinatario->email;
  }

  public function enviar(Destinatario $destinatario, Mensaje $mensaje): void
  {
    $email = (new Email())
      ->from(new Address(Env::get('MAIL_FROM', $this->negocio['email']), $this->negocio['nombre']))
      ->replyTo($this->negocio['email'])
      ->to(new Address((string) $destinatario->email, $destinatario->nombre))
      ->subject($mensaje->asunto)
      ->text($mensaje->texto);

    foreach ($mensaje->adjuntos as $adjunto) {
      $email->attach($adjunto['contenido'], $adjunto['nombre'], $adjunto['tipo']);
    }

    try {
      (new Mailer(Transport::fromDsn((string) Env::get('MAIL_DSN'))))->send($email);
    } catch (TransportExceptionInterface $e) {
      throw new RuntimeException('No se pudo enviar el email: ' . $e->getMessage(), 0, $e);
    }
  }
}
