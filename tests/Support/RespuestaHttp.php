<?php

declare(strict_types=1);

namespace Tests\Support;

/** Lo que respondió el sistema a un pedido de HttpTestCase::pedir(). */
final class RespuestaHttp
{
  public function __construct(
    public readonly int $estado,
    public readonly ?string $destino,
    private readonly string $cuerpo,
  ) {
  }

  /** @return mixed el JSON decodificado */
  public function json(): mixed
  {
    return json_decode($this->cuerpo, true);
  }

  /** HTML de la página (o el cuerpo crudo de la respuesta). */
  public function html(): string
  {
    return $this->cuerpo;
  }
}
