<?php

declare(strict_types=1);

namespace App\Support;

/** Celdas de CSV para abrir en Excel en español. */
final class Csv
{
  /**
   * Los números van con coma decimal (salvo en las columnas de texto indicadas). Un texto
   * que empieza con = + - @ o un tabulador/retorno se antepone con ' para que Excel no lo
   * interprete como fórmula (inyección de fórmulas en CSV).
   */
  public static function celda(mixed $valor, bool $esTexto = false): string
  {
    $texto = (string) $valor;

    if (!$esTexto && is_numeric($valor)) {
      return str_replace('.', ',', $texto);
    }

    return preg_match('/^[=+\-@\t\r]/', $texto) ? "'" . $texto : $texto;
  }
}
