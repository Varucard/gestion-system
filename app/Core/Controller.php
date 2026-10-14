<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Base de los controladores: render, redirecciones, JSON y CSRF.
 */
abstract class Controller
{
  public function __construct(
    protected readonly View $view,
    protected readonly Session $session,
  ) {
  }

  /** @param array<string, mixed> $data */
  protected function render(string $template, array $data = []): void
  {
    echo $this->view->render($template, $data);
  }

  protected function redirect(string $path): never
  {
    throw Respuesta::redireccion(url($path));
  }

  /** Redirección a otro sitio (p. ej. wa.me con el mensaje armado). */
  protected function redirectExterno(string $url): never
  {
    throw Respuesta::redireccion($url);
  }

  protected function json(mixed $data, int $status = 200): never
  {
    throw Respuesta::json($data, $status);
  }

  /**
   * Responde a DataTables con las filas de una consulta paginada.
   *
   * @param array{draw: int, recordsTotal: int, recordsFiltered: int, filas: list<array<string, mixed>>} $resultado
   * @param string $template  template de fila que devuelve las celdas
   * @param string $variable  nombre con el que el template recibe cada fila
   */
  protected function tabla(array $resultado, string $template, string $variable, array $extra = []): never
  {
    $this->json([
      'draw' => $resultado['draw'],
      'recordsTotal' => $resultado['recordsTotal'],
      'recordsFiltered' => $resultado['recordsFiltered'],
      'data' => array_map(fn(array $fila) => $this->view->fila($template, [$variable => $fila, ...$extra]), $resultado['filas']),
    ]);
  }

  /** Corta la petición si el token CSRF no es válido. */
  protected function verifyCsrf(Request $request): void
  {
    $token = $request->input('_token') ?? $request->header('X-CSRF-Token');

    if (!$this->session->validCsrf(is_string($token) ? $token : null)) {
      http_response_code(419);
      // Si lo enviado supera post_max_size, PHP descarta todo (también el token): no es la sesión.
      if (self::superoElLimite()) {
        throw new ValidationException([sprintf(
          'Lo que mandaste pesa demasiado (más de %s entre todo, casi siempre por las fotos). Probá de nuevo con menos fotos.',
          ini_get('post_max_size') . 'B',
        )]);
      }
      throw new ValidationException(['La sesión expiró. Recargá la página e intentá de nuevo.']);
    }
  }

  /** ¿El pedido llegó vacío porque pesaba más que post_max_size? */
  private static function superoElLimite(): bool
  {
    $largo = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $limite = trim((string) ini_get('post_max_size'));
    $bytes = (int) $limite * match (strtoupper(substr($limite, -1))) { 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1 };

    return $_POST === [] && $bytes > 0 && $largo > $bytes;
  }

  protected function success(string $message): void
  {
    $this->session->flash('success', $message);
  }

  /**
   * Aviso de éxito con botón "Deshacer" (en vez de preguntar "¿Seguro?" antes): la acción ya
   * se hizo y, por unos segundos, se puede revertir con un POST a $accion con $campos.
   * Solo para lo que se revierte de verdad (no para lo que avisa al cliente o borra).
   *
   * @param array<string, scalar> $campos
   */
  protected function hechoConDeshacer(string $mensaje, string $accion, array $campos = []): void
  {
    $this->session->flash('deshacer', (string) json_encode(['mensaje' => $mensaje, 'accion' => $accion, 'campos' => $campos + ['deshaciendo' => '1']]));
  }

  /** Aviso amarillo: lo principal salió bien, pero hay algo que la persona tiene que saber o hacer. */
  protected function aviso(string $message): void
  {
    $this->session->flash('aviso', $message);
  }

  protected function error(string $message): void
  {
    $this->session->flash('error', $message);
  }

  /**
   * Vuelve al formulario conservando lo cargado y mostrando los errores: los que son de un campo,
   * debajo de ese campo (componentes/campo, con un resumen arriba); el resto, arriba.
   */
  protected function backWithErrors(string $path, ValidationException $e, Request $request): never
  {
    $deCampo = $e->campos();
    $sueltos = array_values(array_filter($e->errors(), fn(string $m) => !in_array($m, $deCampo, true)));
    if ($deCampo !== []) {
      $this->session->flash('campos', (string) json_encode($deCampo, JSON_UNESCAPED_UNICODE));
      $this->error(count($deCampo) === 1 ? 'Hay un dato para corregir: está marcado en rojo abajo.' : 'Hay ' . count($deCampo) . ' datos para corregir: están marcados en rojo abajo.');
    }
    foreach ($sueltos as $message) {
      $this->error($message);
    }
    $this->session->keepInput($request->all());

    $this->redirect($path);
  }
}
