<?php

declare(strict_types=1);

namespace App\Notificaciones;

use LogicException;

/**
 * WhatsApp Business (Cloud API de Meta) — PREPARADO, TODAVÍA NO ACTIVO.
 *
 * Para activarlo hace falta:
 *  1. Cuenta de WhatsApp Business verificada en Meta y un número dedicado.
 *  2. Plantillas de mensaje aprobadas por Meta (los avisos que inicia la empresa
 *     solo pueden enviarse con plantillas aprobadas).
 *  3. Variables WHATSAPP_TOKEN y WHATSAPP_PHONE_ID en .env.
 *  4. Implementar enviar(): POST https://graph.facebook.com/v{versión}/{PHONE_ID}/messages
 *     con el nombre de la plantilla y sus parámetros.
 *
 * Mientras tanto disponible() devuelve false y el sistema usa los demás canales.
 */
final class WhatsAppCanal implements CanalNotificacion
{
  public function __construct(private readonly string $codigoPais = '54')
  {
  }

  public function nombre(): string
  {
    return 'whatsapp';
  }

  public function disponible(): bool
  {
    // Se habilita cuando enviar() esté implementado y existan WHATSAPP_TOKEN y WHATSAPP_PHONE_ID.
    return false;
  }

  public function puedeEnviarA(Destinatario $destinatario): bool
  {
    return $destinatario->telefono !== null && strlen(preg_replace('/\D/', '', $destinatario->telefono)) >= 10;
  }

  public function destino(Destinatario $destinatario): string
  {
    return telefono_internacional((string) $destinatario->telefono, $this->codigoPais);
  }

  public function enviar(Destinatario $destinatario, Mensaje $mensaje): void
  {
    throw new LogicException('El canal de WhatsApp todavía no está implementado.');
  }
}
