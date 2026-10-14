<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\EmpleadoRepository;
use App\Repositories\MarcaRepository;
use App\Repositories\RepuestoRepository;
use App\Repositories\ServicioRepository;
use App\Repositories\UsuarioRepository;

/**
 * Primeros pasos: lo que conviene dejar cargado al empezar a usar el sistema, en orden, con
 * lo que ya está hecho tildado (se fija solo, no hay que marcarlo). Se ve en el inicio de los
 * administradores hasta que se completan los necesarios o se ocultan.
 */
final class PrimerosPasosService
{
  private const OCULTOS = 'primeros_pasos_ocultos';

  public function __construct(
    private readonly ConfiguracionService $configuracion,
    private readonly ServicioRepository $servicios,
    private readonly MarcaRepository $marcas,
    private readonly EmpleadoRepository $empleados,
    private readonly UsuarioRepository $usuarios,
    private readonly RepuestoRepository $repuestos,
    private readonly NotificacionService $notificaciones,
    private readonly MarcaService $marcaService,
    private readonly ModeloService $modeloService,
  ) {
  }

  /**
   * @return list<array{clave: string, titulo: string, para: string, ruta: string, boton: string, hecho: bool, opcional: bool}>
   */
  public function pasos(): array
  {
    $paso = fn(string $clave, string $titulo, string $para, string $ruta, string $boton, bool $hecho, bool $opcional = false) =>
      compact('clave', 'titulo', 'para', 'ruta', 'boton', 'hecho', 'opcional');

    return [
      $paso('negocio', 'Datos del negocio', 'Salen en los presupuestos, los comprobantes y los mensajes a los clientes.',
        'configuracion/negocio', 'Cargar los datos', $this->configuracion->fueGuardada('negocio')),
      $paso('horario', 'Horario de atención', 'Para dar turnos solo cuando el negocio está abierto, y cuántos equipos se atienden a la vez.',
        'configuracion/turnos', 'Revisar el horario', $this->configuracion->fueGuardada('turnos')),
      $paso('servicios', 'Servicios y precios', 'Lo que hace el negocio (limpieza, formateo, cambio de pantalla…) con su precio: se eligen al armar el presupuesto.',
        'servicios', 'Cargar servicios', $this->servicios->hayAlguno()),
      $paso('marcas', 'Marcas y modelos', 'Para cargar los equipos eligiendo de una lista. Se pueden cargar las más comunes de un clic.',
        'primeros-pasos#marcas', 'Cargar marcas', $this->marcas->hayAlguno()),
      $paso('empleados', 'Técnicos', 'Para saber quién atiende cada equipo.', 'empleados/crear', 'Cargar un técnico', $this->empleados->activos() !== [], true),
      $paso('usuarios', 'Usuarios del equipo', 'Cada persona entra con el suyo (y, si quiere, con un PIN en el mostrador).',
        'usuarios/crear', 'Crear usuarios', $this->usuarios->contar() > 1, true),
      $paso('repuestos', 'Repuestos', 'Con su stock, el sistema avisa cuando hay que reponer.', 'repuestos', 'Cargar repuestos', $this->repuestos->hayAlguno(), true),
      $paso('email', 'Email para los avisos', 'Para mandar presupuestos, confirmar turnos y avisar que el equipo está listo. Lo configura quien instaló el sistema.',
        'configuracion/notificaciones', 'Ver cómo está', $this->notificaciones->hayCanalDisponible(), true),
    ];
  }

  /**
   * ¿Se muestra en el inicio? Mientras falte algo necesario y no se haya ocultado.
   *
   * @param list<array<string, mixed>>|null $pasos los ya calculados (para no consultar dos veces)
   */
  public function mostrarEnElInicio(?array $pasos = null): bool
  {
    if ($this->configuracion->estado(self::OCULTOS)) {
      return false;
    }

    return array_filter($pasos ?? $this->pasos(), fn(array $p) => !$p['hecho'] && !$p['opcional']) !== [];
  }

  public function ocultos(): bool
  {
    return (bool) $this->configuracion->estado(self::OCULTOS);
  }

  public function ocultar(): void
  {
    $this->configuracion->recordar(self::OCULTOS, true);
  }

  /**
   * Carga las marcas y modelos más comunes (config/marcas_comunes.php). Lo que ya existe se
   * deja como está. Devuelve cuántos modelos se agregaron.
   */
  public function cargarMarcasComunes(): int
  {
    $agregados = 0;
    foreach (require dirname(__DIR__, 2) . '/config/marcas_comunes.php' as $marca => $modelos) {
      $marcaId = $this->marcaService->obtenerOCrear($marca);
      foreach ($modelos as $modelo) {
        try {
          $this->modeloService->guardar($marcaId, $modelo);
          $agregados++;
        } catch (ValidationException) {
          // ya estaba
        }
      }
    }

    return $agregados;
  }
}
