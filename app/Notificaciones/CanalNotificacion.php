<?php

declare(strict_types=1);

namespace App\Notificaciones;

/**
 * Medio por el que se avisa al cliente (email, WhatsApp, …).
 *
 * Para sumar un canal nuevo: implementar esta interfaz, registrarlo en
 * NotificacionService::canal() y agregar sus plantillas en config/negocio.php.
 */
interface CanalNotificacion
{
  /** Identificador: "email", "whatsapp". */
  public function nombre(): string;

  /** ¿Está configurado y listo para enviar? */
  public function disponible(): bool;

  /** ¿El destinatario tiene los datos que este canal necesita? */
  public function puedeEnviarA(Destinatario $destinatario): bool;

  /** Dirección a la que se envió (para el registro): email, teléfono, … */
  public function destino(Destinatario $destinatario): string;

  /** @throws \RuntimeException si el envío falla */
  public function enviar(Destinatario $destinatario, Mensaje $mensaje): void;
}
