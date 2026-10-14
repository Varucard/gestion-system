<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta que corta la petición: una redirección o un JSON.
 *
 * Los controladores la lanzan (redirect(), json()) en lugar de hacer exit: App la atrapa y
 * la envía. Así, en los tests se puede llamar a un controlador y ver qué respondió.
 *
 * Extiende Exception (no RuntimeException) a propósito: los controladores atrapan
 * RuntimeException en algunos envíos de avisos y no deben tragarse una redirección.
 */
final class Respuesta extends \Exception
{
  /** @param array<string, string> $encabezados */
  private function __construct(
    public readonly int $estado,
    public readonly array $encabezados,
    public readonly string $cuerpo = '',
  ) {
    parent::__construct("Respuesta {$estado}");
  }

  public static function redireccion(string $destino): self
  {
    return new self(303, ['Location' => $destino]);
  }

  public static function json(mixed $datos, int $estado = 200): self
  {
    return new self($estado, ['Content-Type' => 'application/json; charset=utf-8'], (string) json_encode($datos, JSON_UNESCAPED_UNICODE));
  }

  /** Destino de la redirección (null si no es una). */
  public function destino(): ?string
  {
    return $this->encabezados['Location'] ?? null;
  }

  /** @return mixed el JSON decodificado (para los tests) */
  public function datos(): mixed
  {
    return json_decode($this->cuerpo, true);
  }

  public function enviar(): void
  {
    if (!headers_sent()) {
      http_response_code($this->estado);
      foreach ($this->encabezados as $nombre => $valor) {
        header("{$nombre}: {$valor}");
      }
    }
    echo $this->cuerpo;
  }
}
