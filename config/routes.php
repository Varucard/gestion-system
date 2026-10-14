<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AyudaController;
use App\Controllers\BusquedaController;
use App\Controllers\ClienteController;
use App\Controllers\ComboController;
use App\Controllers\ConfiguracionController;
use App\Controllers\EmpleadoController;
use App\Controllers\EncuestaController;
use App\Controllers\EntregaController;
use App\Controllers\HomeController;
use App\Controllers\MarcaController;
use App\Controllers\ModeloController;
use App\Controllers\MostradorController;
use App\Controllers\OrdenController;
use App\Controllers\PagoController;
use App\Controllers\PrecioController;
use App\Controllers\PrimerosPasosController;
use App\Controllers\ProveedorController;
use App\Controllers\PublicoController;
use App\Controllers\PwaController;
use App\Controllers\RecepcionController;
use App\Controllers\RegistroController;
use App\Controllers\ReporteController;
use App\Controllers\RepuestoController;
use App\Controllers\ServicioController;
use App\Controllers\TurnoController;
use App\Controllers\UsuarioController;
use App\Controllers\EquipoController;
use App\Core\Router;

return function (Router $r): void {
  // Acceso
  $r->get('/login', [AuthController::class, 'loginForm'], Router::ACCESO_PUBLICO);
  $r->post('/login', [AuthController::class, 'login'], Router::ACCESO_PUBLICO);
  $r->post('/login/pin', [AuthController::class, 'loginPin'], Router::ACCESO_PUBLICO);
  $r->post('/logout', [AuthController::class, 'logout']);
  $r->get('/instalacion', [AuthController::class, 'setupForm'], Router::ACCESO_PUBLICO);
  $r->post('/instalacion', [AuthController::class, 'setup'], Router::ACCESO_PUBLICO);
  // Páginas públicas para clientes
  $publico = Router::ACCESO_PUBLICO;
  $r->get('/turno/{token:token}', [PublicoController::class, 'turno'], $publico);
  $r->post('/turno/{token:token}/confirmar', [PublicoController::class, 'confirmarTurno'], $publico);
  $r->post('/turno/{token:token}/cancelar', [PublicoController::class, 'cancelarTurno'], $publico);
  $r->get('/presupuesto/{token:token}', [PublicoController::class, 'presupuesto'], $publico);
  $r->get('/presupuesto/{token:token}/pdf', [PublicoController::class, 'presupuestoPdf'], $publico);
  $r->post('/presupuesto/{token:token}', [PublicoController::class, 'responderPresupuesto'], $publico);
  $r->get('/presupuesto/{token:token}/seguimiento', [PublicoController::class, 'seguimientoPresupuesto'], $publico);
  $r->get('/seguimiento', [PublicoController::class, 'seguimiento'], $publico);
  // App instalable (PWA)
  $r->get('/app.webmanifest', [PwaController::class, 'app'], $publico);
  $r->get('/portal.webmanifest', [PwaController::class, 'portal'], $publico);
  $r->post('/seguimiento', [PublicoController::class, 'consultar'], $publico);

  $r->get('/perfil/clave', [UsuarioController::class, 'claveForm']);
  $r->post('/perfil/clave', [UsuarioController::class, 'cambiarClave']);
  $r->post('/perfil/pin', [UsuarioController::class, 'definirPin']);
  $r->post('/perfil/pin/quitar', [UsuarioController::class, 'quitarPin']);

  $r->get('/', [HomeController::class, 'index']);
  // La pantalla del mostrador avisa que sigue en uso (la sesión se renueva al pasar por el guard).
  $r->get('/sesion', [HomeController::class, 'sesion']);
  $r->get('/buscar', [BusquedaController::class, 'index']);
  $r->get('/buscar/sugerencias', [BusquedaController::class, 'sugerencias']);

  // Encuesta de usabilidad (SUS)
  $r->get('/encuesta', [EncuestaController::class, 'create']);
  $r->post('/encuesta', [EncuestaController::class, 'store']);
  $r->post('/encuesta/despues', [EncuestaController::class, 'despues']);

  // Ayuda: el manual de usuario
  $r->get('/ayuda', [AyudaController::class, 'index']);
  $r->get('/ayuda/imprimir', [AyudaController::class, 'imprimir']);
  $r->get('/ayuda/capturas/{nombre:captura}', [AyudaController::class, 'captura']);

  // Llegó un equipo: recepción en un solo paso
  $r->get('/recepcion', [RecepcionController::class, 'create']);
  $r->post('/recepcion', [RecepcionController::class, 'store']);
  $r->get('/recepcion/cliente', [RecepcionController::class, 'cliente']);

  // Clientes
  $r->get('/clientes', [ClienteController::class, 'index']);
  $r->get('/clientes/datos', [ClienteController::class, 'datos']);
  $r->get('/clientes/crear', [ClienteController::class, 'create']);
  $r->post('/clientes', [ClienteController::class, 'store']);
  $r->get('/clientes/{id}', [ClienteController::class, 'show']);
  $r->get('/clientes/{id}/editar', [ClienteController::class, 'edit']);
  $r->get('/clientes/{id}/foto', [ClienteController::class, 'foto']);
  $r->post('/clientes/{id}/foto', [ClienteController::class, 'subirFoto']);
  $r->post('/clientes/{id}/foto/eliminar', [ClienteController::class, 'quitarFoto']);
  $r->post('/clientes/{id}', [ClienteController::class, 'update']);
  $r->post('/clientes/{id}/estado', [ClienteController::class, 'toggle']);
  $r->post('/clientes/{id}/eliminar', [ClienteController::class, 'destroy']);
  $r->get('/clientes/{clienteId}/equipos', [EquipoController::class, 'porCliente']);

  // Equipos
  $r->get('/equipos', [EquipoController::class, 'index']);
  $r->get('/equipos/datos', [EquipoController::class, 'datos']);
  $r->get('/equipos/crear', [EquipoController::class, 'create']);
  $r->post('/equipos', [EquipoController::class, 'store']);
  $r->get('/equipos/{id}', [EquipoController::class, 'show']);
  $r->get('/equipos/{id}/editar', [EquipoController::class, 'edit']);
  $r->post('/equipos/{id}/imagenes', [EquipoController::class, 'subirImagen']);
  $r->get('/equipos/{id}/imagenes/{imagenId}', [EquipoController::class, 'imagen']);
  $r->post('/imagenes/{imagenId}/eliminar', [EquipoController::class, 'eliminarImagen']);
  $r->post('/equipos/{id}', [EquipoController::class, 'update']);
  $r->post('/equipos/{id}/estado', [EquipoController::class, 'toggle']);

  // Catálogos
  foreach (['marcas' => MarcaController::class, 'modelos' => ModeloController::class,
            'servicios' => ServicioController::class, 'repuestos' => RepuestoController::class] as $path => $controller) {
    $r->get("/{$path}", [$controller, 'index']);
    $r->post("/{$path}", [$controller, 'store']);
    $r->get("/{$path}/{id}/editar", [$controller, 'edit']);
    $r->post("/{$path}/{id}", [$controller, 'update']);
    $r->post("/{$path}/{id}/eliminar", [$controller, 'destroy']);
  }
  $r->get('/marcas/{marcaId}/modelos', [EquipoController::class, 'modelosPorMarca']);
  $r->get('/combos', [ComboController::class, 'index']);
  $r->get('/combos/crear', [ComboController::class, 'create']);
  $r->post('/combos', [ComboController::class, 'store']);
  $r->get('/combos/{id}/editar', [ComboController::class, 'edit']);
  $r->post('/combos/{id}', [ComboController::class, 'update']);
  $r->post('/combos/{id}/eliminar', [ComboController::class, 'destroy']);

  // Stock y proveedores
  $r->get('/repuestos/{id}/stock', [RepuestoController::class, 'stock']);
  $r->post('/repuestos/{id}/ingresos', [RepuestoController::class, 'ingresar']);
  $r->post('/repuestos/{id}/ajustes', [RepuestoController::class, 'ajustar']);
  $r->get('/proveedores', [ProveedorController::class, 'index']);
  $r->get('/proveedores/crear', [ProveedorController::class, 'create']);
  $r->post('/proveedores', [ProveedorController::class, 'store']);
  $r->get('/proveedores/{id}/editar', [ProveedorController::class, 'edit']);
  $r->post('/proveedores/{id}', [ProveedorController::class, 'update']);
  $r->post('/proveedores/{id}/eliminar', [ProveedorController::class, 'destroy']);

  // Órdenes y presupuestos
  $r->get('/ordenes', [OrdenController::class, 'index']);
  $r->get('/ordenes/datos', [OrdenController::class, 'datos']);
  $r->get('/ordenes/tablero', [OrdenController::class, 'tablero']);
  $r->get('/ordenes/crear', [OrdenController::class, 'create']);
  $r->post('/ordenes', [OrdenController::class, 'store']);
  $r->get('/ordenes/{id}', [OrdenController::class, 'show']);
  $r->get('/ordenes/{id}/editar', [OrdenController::class, 'edit']);
  $r->post('/ordenes/{id}/pagos', [PagoController::class, 'store']);
  $r->post('/ordenes/{id}/enviar-presupuesto', [OrdenController::class, 'enviarPresupuesto']);
  $r->post('/ordenes/{id}/whatsapp/{tipo:slug}', [OrdenController::class, 'whatsapp']);
  $r->get('/deudores', [PagoController::class, 'deudores']);
  $r->post('/ordenes/{id}', [OrdenController::class, 'update']);
  $r->post('/ordenes/{id}/estado', [OrdenController::class, 'cambiarEstado']);
  $r->get('/ordenes/{id}/presupuesto', [OrdenController::class, 'presupuesto']);
  $r->get('/ordenes/{id}/presupuesto/pdf', [OrdenController::class, 'pdf']);
  $r->get('/ordenes/{id}/entrega', [OrdenController::class, 'entrega']);
  $r->get('/ordenes/{id}/entrega/pdf', [OrdenController::class, 'entregaPdf']);
  $r->get('/ordenes/{id}/ticket/{tipo:slug}', [OrdenController::class, 'ticket']);
  // Cómo llegó (fotos y firma al recibir) y entrega al cliente
  $r->post('/ordenes/{id}/fotos', [RecepcionController::class, 'fotos']);
  $r->get('/ordenes/{id}/firmar', [RecepcionController::class, 'firmarForm']);
  $r->post('/ordenes/{id}/firmar', [RecepcionController::class, 'firmar']);
  $r->get('/ordenes/{id}/entregar', [EntregaController::class, 'create']);
  $r->post('/ordenes/{id}/entregar', [EntregaController::class, 'store']);
  $r->post('/ordenes/{id}/entrega/anular', [EntregaController::class, 'anular']);
  $r->get('/ordenes/{id}/firmas/{momento:slug}', [EntregaController::class, 'firma']);

  // Turnos
  $r->get('/turnos', [TurnoController::class, 'index']);
  $r->get('/turnos/datos', [TurnoController::class, 'datos']);
  $r->get('/turnos/crear', [TurnoController::class, 'create']);
  $r->get('/turnos/semana', [TurnoController::class, 'semana']);
  $r->get('/turnos/horarios', [TurnoController::class, 'horarios']);
  $r->post('/turnos', [TurnoController::class, 'store']);
  $r->get('/turnos/{id}/editar', [TurnoController::class, 'edit']);
  $r->post('/turnos/{id}', [TurnoController::class, 'update']);
  $r->post('/turnos/{id}/estado', [TurnoController::class, 'cambiarEstado']);
  $r->post('/turnos/{id}/eliminar', [TurnoController::class, 'destroy']);
  $r->post('/turnos/{id}/whatsapp', [TurnoController::class, 'whatsapp']);
  $r->post('/turnos/{id}/recordar', [TurnoController::class, 'recordar']);
  $r->post('/turnos/{id}/confirmacion', [TurnoController::class, 'pedirConfirmacion']);

  // Solo administradores
  $admin = Router::ACCESO_ADMIN;
  $r->get('/precios', [PrecioController::class, 'index'], $admin);
  $r->post('/precios/vista-previa', [PrecioController::class, 'vistaPrevia'], $admin);
  $r->post('/precios/aplicar', [PrecioController::class, 'aplicar'], $admin);
  $r->get('/reportes', [ReporteController::class, 'index'], $admin);
  $r->get('/reportes/{reporte:slug}/csv', [ReporteController::class, 'csv'], $admin);
  $r->get('/auditoria', [RegistroController::class, 'auditoria'], $admin);
  $r->get('/auditoria/datos', [RegistroController::class, 'auditoriaDatos'], $admin);
  $r->get('/logs', [RegistroController::class, 'logs'], $admin);
  $r->get('/configuracion', [ConfiguracionController::class, 'index'], $admin);
  $r->post('/configuracion/feriados/importar', [ConfiguracionController::class, 'importarFeriados'], $admin);
  $r->post('/configuracion/backups/generar', [ConfiguracionController::class, 'generarBackup'], $admin);
  $r->get('/configuracion/backups/descargar', [ConfiguracionController::class, 'descargarBackup'], $admin);
  $r->get('/configuracion/{seccion:slug}', [ConfiguracionController::class, 'edit'], $admin);
  $r->post('/configuracion/{seccion:slug}', [ConfiguracionController::class, 'update'], $admin);
  $r->get('/usabilidad', [EncuestaController::class, 'resultados'], $admin);
  $r->post('/usabilidad/abrir', [EncuestaController::class, 'abrir'], $admin);
  $r->post('/usabilidad/cerrar', [EncuestaController::class, 'cerrar'], $admin);
  $r->get('/primeros-pasos', [PrimerosPasosController::class, 'index'], $admin);
  $r->post('/primeros-pasos/marcas', [PrimerosPasosController::class, 'cargarMarcas'], $admin);
  $r->post('/primeros-pasos/ocultar', [PrimerosPasosController::class, 'ocultar'], $admin);
  $r->get('/mostrador', [MostradorController::class, 'index'], $admin);
  $r->post('/mostrador', [MostradorController::class, 'habilitar'], $admin);
  $r->post('/mostrador/{id}/quitar', [MostradorController::class, 'quitar'], $admin);
  $r->get('/usuarios', [UsuarioController::class, 'index'], $admin);
  $r->get('/usuarios/crear', [UsuarioController::class, 'create'], $admin);
  $r->post('/usuarios', [UsuarioController::class, 'store'], $admin);
  $r->get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'], $admin);
  $r->post('/usuarios/{id}', [UsuarioController::class, 'update'], $admin);
  $r->post('/pagos/{id}/anular', [PagoController::class, 'destroy'], $admin);
  $r->get('/empleados', [EmpleadoController::class, 'index'], $admin);
  $r->get('/empleados/crear', [EmpleadoController::class, 'create'], $admin);
  $r->post('/empleados', [EmpleadoController::class, 'store'], $admin);
  $r->get('/empleados/{id}/editar', [EmpleadoController::class, 'edit'], $admin);
  $r->post('/empleados/{id}', [EmpleadoController::class, 'update'], $admin);
  $r->post('/empleados/{id}/estado', [EmpleadoController::class, 'toggle'], $admin);
};
