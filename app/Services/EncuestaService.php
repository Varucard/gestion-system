<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EncuestaRepository;
use App\Support\Validator;

/**
 * Encuesta de usabilidad SUS (System Usability Scale, John Brooke, 1986): 10 afirmaciones que
 * se responden de 1 (nada de acuerdo) a 5 (muy de acuerdo). Las impares son positivas y las
 * pares negativas; el puntaje va de 0 a 100 y el promedio de la industria es 68.
 *
 * Se toma por rondas que abre un administrador (p. ej. "Antes de los cambios" y "Después de
 * los cambios"): mientras hay una abierta, el sistema invita a cada usuario a responderla
 * una vez. Así se compara si lo que se cambió mejoró de verdad.
 */
final class EncuestaService
{
  public const PREGUNTAS = [
    1 => 'Me gustaría usar este sistema seguido.',
    2 => 'El sistema me pareció más complicado de lo necesario.',
    3 => 'El sistema me pareció fácil de usar.',
    4 => 'Creo que necesitaría que alguien que sabe me ayude para poder usarlo.',
    5 => 'Las distintas partes del sistema están bien conectadas entre sí.',
    6 => 'Me pareció que el sistema tiene muchas cosas que no son coherentes.',
    7 => 'Me imagino que la mayoría de la gente aprendería a usarlo muy rápido.',
    8 => 'El sistema me pareció muy engorroso de usar.',
    9 => 'Me sentí con confianza usando el sistema.',
    10 => 'Tuve que aprender muchas cosas antes de poder arreglarme con el sistema.',
  ];

  /** Promedio de la industria (Sauro, 500 estudios): por encima es mejor que la mayoría. */
  public const PROMEDIO = 68.0;

  private const RONDA = 'encuesta_ronda';

  public function __construct(
    private readonly EncuestaRepository $encuestas,
    private readonly ConfiguracionService $configuracion,
    private readonly Auditor $auditor,
  ) {
  }

  /** Puntaje SUS de 0 a 100 a partir de las 10 respuestas (1 a 5). @param array<int, int> $respuestas */
  public static function puntaje(array $respuestas): float
  {
    $suma = 0;
    foreach (array_keys(self::PREGUNTAS) as $n) {
      $suma += $n % 2 === 1 ? $respuestas[$n] - 1 : 5 - $respuestas[$n];
    }

    return $suma * 2.5;
  }

  /** Cómo se lee un puntaje (escala de adjetivos de Bangor, Kortum y Miller). */
  public static function lectura(float $puntaje): string
  {
    return match (true) {
      $puntaje >= 80.3 => 'Excelente',
      $puntaje >= 68 => 'Bueno',
      $puntaje >= 51 => 'Regular: hay que mejorar',
      default => 'Malo: cuesta usarlo',
    };
  }

  public function rondaAbierta(): ?string
  {
    $ronda = $this->configuracion->estado(self::RONDA);

    return is_string($ronda) && $ronda !== '' ? $ronda : null;
  }

  public function abrirRonda(string $nombre): void
  {
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
    (new Validator())->check(Validator::largo($nombre, 3, 60), 'Poné un nombre a la ronda (por ejemplo, "Antes de los cambios").')->validate();

    $this->configuracion->recordar(self::RONDA, $nombre);
    $this->auditor->registrar('abrir_encuesta', 'sistema', null, "Se abrió la encuesta de usabilidad \"{$nombre}\"");
  }

  public function cerrarRonda(): void
  {
    $this->configuracion->recordar(self::RONDA, null);
    $this->auditor->registrar('cerrar_encuesta', 'sistema', null, 'Se cerró la encuesta de usabilidad');
  }

  /** ¿Hay que invitar a este usuario a responder? */
  public function pendientePara(int $usuarioId): bool
  {
    $ronda = $this->rondaAbierta();

    return $ronda !== null && !$this->encuestas->respondio($usuarioId, $ronda);
  }

  /** @param array<string, mixed> $input  p1…p10 (1 a 5) y comentario */
  public function responder(int $usuarioId, array $input): float
  {
    $ronda = $this->rondaAbierta();
    $respuestas = [];
    foreach (array_keys(self::PREGUNTAS) as $n) {
      $valor = filter_var($input["p{$n}"] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
      $respuestas[$n] = $valor === false ? null : $valor;
    }
    $comentario = Validator::nullable((string) ($input['comentario'] ?? ''));

    (new Validator())
      ->check($ronda !== null, 'La encuesta ya se cerró. ¡Gracias igual!')
      ->check(!in_array(null, $respuestas, true), 'Respondé las 10 preguntas (de 1 a 5).')
      ->check($comentario === null || mb_strlen($comentario) <= 2000, 'El comentario puede tener hasta 2000 caracteres.')
      ->check($ronda === null || !$this->encuestas->respondio($usuarioId, $ronda), 'Ya respondiste esta ronda de la encuesta. ¡Gracias!')
      ->validate();

    $puntaje = self::puntaje($respuestas);
    try {
      $this->encuestas->guardar($usuarioId, $ronda, implode(',', $respuestas), $puntaje, $comentario);
    } catch (\PDOException $e) {
      // Doble envío: el segundo choca con la clave única (usuario, ronda).
      throw \App\Repositories\Repository::isDuplicate($e) ? new \App\Exceptions\ValidationException(['Ya respondiste esta ronda de la encuesta. ¡Gracias!']) : $e;
    }

    return $puntaje;
  }

  /**
   * Resultados por ronda, para comparar, con el promedio de cada pregunta y los comentarios.
   *
   * @return list<array<string, mixed>>
   */
  public function resultados(): array
  {
    return array_map(function (array $r) {
      $filas = $this->encuestas->deRonda($r['ronda']);
      $porPregunta = array_fill_keys(array_keys(self::PREGUNTAS), 0.0);
      foreach ($filas as $f) {
        foreach (explode(',', $f['respuestas']) as $i => $valor) {
          $porPregunta[$i + 1] += (int) $valor / count($filas);
        }
      }

      return [
        ...$r,
        'promedio' => round((float) $r['promedio'], 1),
        'lectura' => self::lectura((float) $r['promedio']),
        'porPregunta' => array_map(fn(float $v) => round($v, 1), $porPregunta),
        'comentarios' => array_values(array_filter(array_column($filas, 'comentario'))),
      ];
    }, $this->encuestas->resumenPorRonda());
  }
}
