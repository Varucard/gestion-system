<?php

declare(strict_types=1);

/**
 * Carga datos ficticios para probar el sistema o sacar capturas del manual:
 * usuarios, técnicos, catálogo, stock, clientes, equipos, órdenes en todos los
 * estados, pagos, turnos y mantenimientos por vencer.
 *
 *   php bin/demo.php
 *
 * Todo pasa por los servicios de la aplicación (mismas validaciones que la
 * interfaz). Solo corre sobre una base sin clientes: nunca mezcla datos de
 * prueba con datos reales.
 */

use App\Core\App;
use App\Enums\Rol;
use App\Services\ClienteService;
use App\Services\ComboService;
use App\Services\ConfiguracionService;
use App\Services\EmpleadoService;
use App\Services\EquipoService;
use App\Services\MarcaService;
use App\Services\ModeloService;
use App\Services\OrdenService;
use App\Services\PagoService;
use App\Services\ProveedorService;
use App\Services\RepuestoService;
use App\Services\ServicioService;
use App\Services\StockService;
use App\Services\TurnoService;
use App\Services\UsuarioService;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = App::boot(dirname(__DIR__))->container;
$db = $container->get(PDO::class);

if ((int) $db->query('SELECT COUNT(*) FROM clientes')->fetchColumn() > 0) {
  fwrite(STDERR, "La base ya tiene clientes: los datos de prueba solo se cargan en una base vacía.\n");
  exit(1);
}

$servicio = fn(string $clase) => $container->get($clase);
$hace = fn(int $dias, string $hora = '10:00') => date('Y-m-d', strtotime("-{$dias} days")) . " {$hora}:00";

// ---------- Datos del negocio (solo si siguen los de fábrica) ----------

$configuracion = $servicio(ConfiguracionService::class);
if (str_starts_with($configuracion->seccion('negocio')['direccion'], 'Completar')) {
  $configuracion->guardar('negocio', [
    'nombre' => 'Servicio Técnico PC', 'cuit' => '20-12345678-9', 'direccion' => 'Av. Rivadavia 14500, Ramos Mejía',
    'telefono' => '11-4654-0000', 'whatsapp' => '+5491146540000', 'email' => 'contacto@serviciotecnicopc.example.com',
  ]);
}

// ---------- Usuarios y técnicos ----------

$usuarios = $servicio(UsuarioService::class);
$admin = null;
if (!$usuarios->hayUsuarios()) {
  $admin = $usuarios->crear(['nombre' => 'Administrador', 'usuario' => 'admin', 'clave' => 'demo1234', 'clave_confirmacion' => 'demo1234'], Rol::Administrador);
}
$usuarios->crear(['nombre' => 'Lucía Fernández', 'usuario' => 'lucia', 'rol' => 'empleado', 'clave' => 'demo1234', 'clave_confirmacion' => 'demo1234']);

$empleados = $servicio(EmpleadoService::class);
$tecnicos = [];
foreach ([
  ['Martín', 'Ríos', '28456123', 'Técnico', '1145678901', '2023-03-01'],
  ['Sofía', 'Acosta', '33987654', 'Técnica', '1156789012', '2024-08-15'],
  ['Diego', 'Paz', '31222333', 'Técnico de impresoras', '1167890123', '2025-02-10'],
] as [$nombre, $apellido, $dni, $puesto, $telefono, $ingreso]) {
  $empleados->crear(compact('nombre', 'apellido', 'dni', 'puesto', 'telefono') + ['fecha_ingreso' => $ingreso]);
  $tecnicos[] = (int) $db->query("SELECT e.id FROM empleados e INNER JOIN personas p ON p.id = e.persona_id WHERE p.dni = '{$dni}'")->fetchColumn();
}

// ---------- Marcas y modelos ----------

$marcas = $servicio(MarcaService::class);
$modelos = $servicio(ModeloService::class);
$catalogo = [];
foreach ([
  'Lenovo' => ['IdeaPad 3', 'ThinkPad E14', 'IdeaCentre AIO 3'],
  'HP' => ['Pavilion 15', '250 G8', 'LaserJet M111w', 'Ink Tank 315'],
  'Dell' => ['Inspiron 15 3520', 'Vostro 3710', 'P2422H'],
  'Asus' => ['VivoBook 15', 'TUF Gaming F15'],
  'Samsung' => ['Galaxy A54', 'Galaxy Tab A8'],
  'Epson' => ['L3250'],
  'Sony' => ['PlayStation 5'],
  'Genérica' => ['PC armada'],
] as $marca => $lista) {
  $marcaId = $marcas->guardar($marca);
  foreach ($lista as $modelo) {
    $catalogo["{$marca} {$modelo}"] = [$marcaId, $modelos->guardar($marcaId, $modelo)];
  }
}

// ---------- Servicios ----------

$servicios = [];
foreach ([
  ['Diagnóstico', 8000, 'Revisión completa del equipo y presupuesto. Se descuenta si se hace el trabajo.'],
  ['Formateo e instalación de Windows', 25000, 'Incluye drivers, antivirus y programas básicos.'],
  ['Respaldo de datos', 12000, 'Copia de documentos, fotos y correo antes de formatear.'],
  ['Limpieza interna', 15000, 'Limpieza de polvo, ventiladores y disipadores.'],
  ['Cambio de pasta térmica', 9000, 'Pasta térmica de alta conductividad en procesador y placa de video.'],
  ['Cambio de pantalla', 20000, 'Mano de obra; la pantalla se cobra aparte.'],
  ['Cambio de teclado', 14000, 'Mano de obra; el teclado se cobra aparte.'],
  ['Instalación de SSD y clonado', 18000, 'Clonado del disco anterior al nuevo SSD.'],
  ['Reparación de pin de carga', 22000, 'Microsoldadura del conector de carga.'],
  ['Eliminación de virus', 16000, 'Limpieza de malware y optimización del sistema.'],
  ['Mantenimiento de impresora', 13000, 'Limpieza de cabezales y purga de tinta.'],
  ['Armado de PC', 30000, 'Armado, cableado y pruebas de estabilidad.'],
] as [$nombre, $precio, $descripcion]) {
  $servicios[$nombre] = $servicio(ServicioService::class)->guardar(['nombre' => $nombre, 'precio_base' => (string) $precio, 'descripcion' => $descripcion]);
}

// ---------- Proveedores, repuestos y stock ----------

$proveedores = [];
foreach ([
  ['Distribuidora Byte SRL', '30-71234567-8', 'Carla Méndez', '1143210000', 'ventas@byte.example.com', 'Av. Corrientes 1234, CABA'],
  ['Repuestos Notebook Sur', '30-70987654-3', 'Hernán Ruiz', '1149876543', 'pedidos@rnsur.example.com', 'Av. Mitre 850, Avellaneda'],
  ['Insumos Gráficos SA', '30-69876543-2', 'Paula Ibáñez', '1147770000', 'contacto@insumos.example.com', 'Lavalle 456, CABA'],
] as [$nombre, $cuit, $contacto, $telefono, $email, $direccion]) {
  $proveedores[] = $servicio(ProveedorService::class)->guardar(compact('nombre', 'cuit', 'contacto', 'telefono', 'email', 'direccion'));
}

$repuestos = [];
foreach ([
  // nombre, código, costo, precio, stock inicial, mínimo, proveedor
  ['SSD 480 GB SATA', 'SSD-480', 28000, 40000, 6, 2, 0],
  ['SSD 1 TB NVMe', 'SSD-1TB', 52000, 75000, 3, 1, 0],
  ['Memoria RAM 8 GB DDR4 SODIMM', 'RAM-8S', 19000, 27000, 5, 2, 0],
  ['Memoria RAM 16 GB DDR4', 'RAM-16D', 34000, 48000, 2, 1, 0],
  ['Pantalla 15.6" HD 30 pines', 'PAN-156', 55000, 80000, 2, 1, 1],
  ['Teclado Lenovo IdeaPad 3 español', 'TEC-LIP3', 21000, 32000, 1, 1, 1],
  ['Cargador universal 65 W', 'CAR-65', 15000, 23000, 4, 2, 1],
  ['Batería HP Pavilion 15', 'BAT-HP15', 38000, 55000, 0, 1, 1],
  ['Pasta térmica (jeringa)', 'PAS-01', 3000, 5000, 10, 3, 0],
  ['Fuente ATX 600 W', 'FUE-600', 42000, 60000, 2, 1, 0],
  ['Pin de carga USB-C', 'PIN-USBC', 2500, 6000, 8, 3, 1],
  ['Kit de tintas Epson 664 (4 colores)', 'TIN-664', 16000, 24000, 3, 2, 2],
] as [$nombre, $codigo, $costo, $precio, $stockInicial, $minimo, $proveedor]) {
  $id = $servicio(RepuestoService::class)->guardar([
    'nombre' => $nombre, 'codigo' => $codigo, 'precio' => (string) $precio, 'precio_costo' => (string) $costo,
    'stock_minimo' => (string) $minimo, 'proveedor_id' => (string) $proveedores[$proveedor],
  ]);
  if ($stockInicial > 0) {
    $servicio(StockService::class)->ingresar($id, (string) $stockInicial, $proveedores[$proveedor], 'Stock inicial', $admin, (string) $costo);
  }
  $repuestos[$nombre] = $id;
}

$combos = $servicio(ComboService::class);
$combos->guardar(['nombre' => 'Mantenimiento completo', 'descripcion' => 'Limpieza interna y cambio de pasta térmica', 'items' => [
  'servicio' => [$servicios['Limpieza interna'] => '1', $servicios['Cambio de pasta térmica'] => '1'],
  'repuesto' => [$repuestos['Pasta térmica (jeringa)'] => '1'],
]]);
$combos->guardar(['nombre' => 'Upgrade a SSD', 'descripcion' => 'SSD de 480 GB con clonado del disco anterior', 'items' => [
  'servicio' => [$servicios['Instalación de SSD y clonado'] => '1'],
  'repuesto' => [$repuestos['SSD 480 GB SATA'] => '1'],
]]);
$combos->guardar(['nombre' => 'Formateo con respaldo', 'items' => [
  'servicio' => [$servicios['Respaldo de datos'] => '1', $servicios['Formateo e instalación de Windows'] => '1'],
]]);

// ---------- Clientes y equipos ----------

$clientes = [];
foreach ([
  ['Ana', 'Gómez', '30111222', '1122334455', 'ana.gomez@example.com', 'Belgrano 1450, Ramos Mejía'],
  ['Carlos', 'Pereyra', '25888999', '1133445566', 'carlos.pereyra@example.com', 'Rivadavia 8020, CABA'],
  ['Valeria', 'Sosa', '35444555', '1144556677', 'vale.sosa@example.com', null],
  ['Jorge', 'Medina', '22333444', '1155667788', null, 'San Martín 300, Morón'],
  ['Florencia', 'Castro', '38777666', '1166778899', 'flor.castro@example.com', null],
  ['Ricardo', 'Benítez', '20555111', '1177889900', 'rbenitez@example.com', 'Alsina 77, Haedo'],
  ['Camila', 'Romero', '40123456', '1188990011', 'camila.romero@example.com', null],
  ['Estudio Contable', 'Luna', '27999000', '1199001122', 'administracion@estudioluna.example.com', 'Moreno 1020, CABA'],
  ['Tomás', 'Herrera', '42888111', '1123456789', 'tomas.herrera@example.com', null],
  ['Graciela', 'Domínguez', '16444777', '1134567890', null, 'Directorio 2500, CABA'],
] as [$nombre, $apellido, $dni, $telefono, $email, $direccion]) {
  $clientes[$dni] = $servicio(ClienteService::class)->crear(compact('nombre', 'apellido', 'dni', 'telefono', 'email', 'direccion'));
}

$equipos = [];
$equipo = function (string $dni, string $tipo, string $modelo, array $extra = []) use (&$equipos, $clientes, $catalogo, $servicio): int {
  [$marcaId, $modeloId] = $catalogo[$modelo];

  return $equipos[] = $servicio(EquipoService::class)->crear(
    ['cliente_id' => $clientes[$dni], 'tipo' => $tipo, 'marca_id' => $marcaId, 'modelo_id' => $modeloId] + $extra
  );
};

$notebookAna = $equipo('30111222', 'notebook', 'Lenovo IdeaPad 3', ['numero_serie' => 'PF2ABC12', 'procesador' => 'Intel Core i5-1135G7', 'memoria' => '8 GB DDR4', 'almacenamiento' => 'HDD 1 TB', 'color' => 'Gris', 'detalle' => 'Windows 11 Home. Tiene una calcomanía en la tapa.']);
$impresoraAna = $equipo('30111222', 'impresora', 'Epson L3250', ['numero_serie' => 'X5NK012345']);
$pcCarlos = $equipo('25888999', 'pc', 'Genérica PC armada', ['procesador' => 'AMD Ryzen 5 5600G', 'memoria' => '16 GB DDR4', 'almacenamiento' => 'SSD 480 GB', 'detalle' => 'Gabinete negro con vidrio lateral.']);
$notebookValeria = $equipo('35444555', 'notebook', 'HP Pavilion 15', ['numero_serie' => '5CD1234XYZ', 'procesador' => 'Intel Core i7-1165G7', 'memoria' => '16 GB', 'almacenamiento' => 'SSD 512 GB NVMe', 'color' => 'Plateado']);
$celularJorge = $equipo('22333444', 'celular', 'Samsung Galaxy A54', ['numero_serie' => 'R58T90ABCDE', 'color' => 'Negro']);
$tabletJorge = $equipo('22333444', 'tablet', 'Samsung Galaxy Tab A8');
$notebookFlorencia = $equipo('38777666', 'notebook', 'Asus VivoBook 15', ['numero_serie' => 'M1N0CV12345', 'procesador' => 'AMD Ryzen 5 5500U', 'memoria' => '8 GB', 'almacenamiento' => 'SSD 256 GB', 'color' => 'Azul']);
$aioRicardo = $equipo('20555111', 'all_in_one', 'Lenovo IdeaCentre AIO 3', ['numero_serie' => 'MP2AIO777', 'procesador' => 'Intel Core i3-10110U', 'memoria' => '8 GB', 'almacenamiento' => 'HDD 1 TB']);
$consolaCamila = $equipo('40123456', 'consola', 'Sony PlayStation 5', ['numero_serie' => 'CFI1215A01', 'color' => 'Blanco']);
$notebookLuna1 = $equipo('27999000', 'notebook', 'Dell Inspiron 15 3520', ['numero_serie' => 'DL3520AA01', 'procesador' => 'Intel Core i5-1235U', 'memoria' => '8 GB', 'almacenamiento' => 'SSD 256 GB']);
$pcLuna = $equipo('27999000', 'pc', 'Dell Vostro 3710', ['numero_serie' => 'DLV3710B02', 'procesador' => 'Intel Core i5-12400', 'memoria' => '8 GB', 'almacenamiento' => 'SSD 256 GB']);
$monitorLuna = $equipo('27999000', 'monitor', 'Dell P2422H', ['numero_serie' => 'CN0P2422H9']);
$impresoraLuna = $equipo('27999000', 'impresora', 'HP LaserJet M111w', ['numero_serie' => 'VNB3K12345']);
$notebookTomas = $equipo('42888111', 'notebook', 'Asus TUF Gaming F15', ['numero_serie' => 'N3NRKD098765', 'procesador' => 'Intel Core i5-11400H', 'memoria' => '16 GB', 'almacenamiento' => 'SSD 512 GB', 'detalle' => 'Placa de video RTX 3050.']);
$notebookGraciela = $equipo('16444777', 'notebook', 'HP 250 G8', ['numero_serie' => 'CND1450QWE', 'procesador' => 'Intel Celeron N4020', 'memoria' => '4 GB', 'almacenamiento' => 'HDD 500 GB']);

// ---------- Órdenes ----------

$ordenes = $servicio(OrdenService::class);
$pagos = $servicio(PagoService::class);

/**
 * Crea una orden y la lleva al estado indicado; $dias es hace cuánto entró.
 *
 * @param array<string, int|float> $items nombre del servicio o repuesto => cantidad
 */
$orden = function (int $equipoId, array $items, array $detalle, string $estado, int $dias, ?int $tecnico = null, array $cobros = [], ?string $presupuesto = null)
  use ($ordenes, $pagos, $servicios, $repuestos, $db, $hace, $admin): int {
  $s = $r = [];
  foreach ($items as $nombre => $cantidad) {
    isset($servicios[$nombre]) ? $s[$servicios[$nombre]] = ['cantidad' => (string) $cantidad] : $r[$repuestos[$nombre]] = ['cantidad' => (string) $cantidad];
  }
  $id = $ordenes->guardar($equipoId, $s, $r, null, $tecnico, $detalle);

  if ($estado !== 'pendiente') {
    $ordenes->cambiarEstado($id, $estado === 'cancelado' ? 'cancelado' : 'en_proceso', $admin);
  }
  if ($estado === 'finalizado') {
    $ordenes->cambiarEstado($id, 'finalizado', $admin);
  }
  foreach ($cobros as [$monto, $forma, $diasPago]) {
    $pagos->registrar($id, ['monto' => (string) $monto, 'forma_pago' => $forma, 'fecha' => date('Y-m-d', strtotime("-{$diasPago} days"))], $admin);
  }

  // Fechas en el pasado, para que el panel y los reportes tengan historia.
  $finalizada = $estado === 'finalizado' ? date('Y-m-d', strtotime('-' . max(0, $dias - 3) . ' days')) : null;
  $db->prepare('UPDATE ordenes SET created_at = ?, fecha_realizado = COALESCE(?, fecha_realizado) WHERE id = ?')->execute([$hace($dias), $finalizada, $id]);
  if ($presupuesto !== null) {
    $db->prepare('UPDATE ordenes SET presupuesto_enviado = ?, presupuesto_respuesta = ?, presupuesto_respuesta_en = ? WHERE id = ?')
      ->execute([$hace($dias, '12:00'), $presupuesto === 'enviado' ? null : $presupuesto, $presupuesto === 'enviado' ? null : $hace(max(0, $dias - 1), '18:30'), $id]);
  }

  return $id;
};

[$martin, $sofia, $diego] = $tecnicos;
$mantenimiento = fn(int $dias) => date('Y-m-d', strtotime("+{$dias} days"));

// Terminadas y cobradas (para reportes y el historial).
$orden($notebookAna, ['Diagnóstico' => 1, 'Instalación de SSD y clonado' => 1, 'SSD 480 GB SATA' => 1], [
  'falla_reportada' => 'Muy lenta, tarda varios minutos en arrancar.', 'accesorios' => 'Cargador original',
  'estado_ingreso' => 'Tapa con rayones leves.', 'diagnostico' => 'Disco rígido con sectores dañados.',
  'trabajo_realizado' => 'Se reemplazó el HDD por un SSD de 480 GB y se clonó el sistema.',
  'proximo_mantenimiento_fecha' => $mantenimiento(12),
], 'finalizado', 75, $martin, [[66000, 'Transferencia Bancaria', 72]], 'aceptado');
$orden($pcCarlos, ['Limpieza interna' => 1, 'Cambio de pasta térmica' => 1, 'Pasta térmica (jeringa)' => 1], [
  'falla_reportada' => 'Se apaga sola cuando juega.', 'diagnostico' => 'Temperatura del procesador a 95 °C por polvo acumulado.',
  'trabajo_realizado' => 'Limpieza completa y cambio de pasta térmica. En prueba de estrés no supera los 70 °C.',
  'proximo_mantenimiento_fecha' => $mantenimiento(20),
], 'finalizado', 60, $sofia, [[29000, 'Contado', 57]]);
$orden($impresoraLuna, ['Mantenimiento de impresora' => 1], [
  'falla_reportada' => 'Imprime con rayas.', 'trabajo_realizado' => 'Limpieza de rodillos y del tambor.',
], 'finalizado', 50, $diego, [[13000, 'Mercado Pago', 47]]);
$orden($notebookLuna1, ['Formateo e instalación de Windows' => 1, 'Respaldo de datos' => 1, 'Memoria RAM 8 GB DDR4 SODIMM' => 1], [
  'falla_reportada' => 'Ventanas emergentes y muy lenta.', 'accesorios' => 'Cargador y mouse inalámbrico',
  'diagnostico' => 'Malware y solo 8 GB de RAM para el uso del estudio.', 'trabajo_realizado' => 'Respaldo, formateo y ampliación a 16 GB de RAM.',
], 'finalizado', 40, $martin, [[30000, 'Transferencia Bancaria', 39], [34000, 'Transferencia Bancaria', 35]], 'aceptado');
$orden($celularJorge, ['Reparación de pin de carga' => 1, 'Pin de carga USB-C' => 1], [
  'falla_reportada' => 'No carga, hay que mover el cable.', 'estado_ingreso' => 'Pantalla con una astilla en la esquina superior.',
  'trabajo_realizado' => 'Cambio del conector USB-C.',
], 'finalizado', 30, $sofia, [[28000, 'Contado', 28]]);

// Terminadas con saldo pendiente: aparecen en Deudores.
$orden($aioRicardo, ['Diagnóstico' => 1, 'Formateo e instalación de Windows' => 1, 'Instalación de SSD y clonado' => 1, 'SSD 480 GB SATA' => 1], [
  'falla_reportada' => 'Se cuelga al abrir el navegador.', 'accesorios' => 'Teclado y mouse',
  'diagnostico' => 'Disco al 100 % de uso permanente.', 'trabajo_realizado' => 'SSD nuevo con Windows 11 limpio.',
  'proximo_mantenimiento_fecha' => $mantenimiento(25),
], 'finalizado', 20, $martin, [[40000, 'Contado', 18]], 'aceptado');
$orden($notebookGraciela, ['Cambio de teclado' => 1, 'Teclado Lenovo IdeaPad 3 español' => 1], [
  'falla_reportada' => 'Varias teclas no responden después de volcar mate.', 'estado_ingreso' => 'Restos de líquido en el teclado.',
  'trabajo_realizado' => 'Reemplazo del teclado y limpieza de la placa.',
], 'finalizado', 15, $sofia, [[20000, 'Contado', 14]]);

// En curso.
$orden($notebookValeria, ['Diagnóstico' => 1, 'Cambio de pantalla' => 1, 'Pantalla 15.6" HD 30 pines' => 1], [
  'falla_reportada' => 'Pantalla con líneas verticales después de un golpe.', 'accesorios' => 'Cargador original y funda',
  'estado_ingreso' => 'Esquina inferior derecha de la tapa golpeada.', 'diagnostico' => 'Panel LCD dañado; la placa funciona bien con monitor externo.',
  'notas_internas' => 'La pantalla llega del proveedor el jueves.',
], 'en_proceso', 6, $sofia, [[50000, 'Transferencia Bancaria', 5]], 'aceptado');
$orden($notebookTomas, ['Limpieza interna' => 1, 'Cambio de pasta térmica' => 1, 'Pasta térmica (jeringa)' => 2], [
  'falla_reportada' => 'Mucho ruido del ventilador y se calienta jugando.', 'accesorios' => 'Cargador',
], 'en_proceso', 3, $martin);
$orden($pcLuna, ['Diagnóstico' => 1, 'Eliminación de virus' => 1], [
  'falla_reportada' => 'Avisos de antivirus y el correo manda mensajes solo.', 'diagnostico' => 'Infección por troyano en el perfil de usuario.',
], 'en_proceso', 2, $martin, [], 'aceptado');

// Pendientes de aprobación.
$orden($notebookFlorencia, ['Diagnóstico' => 1, 'Formateo e instalación de Windows' => 1, 'Respaldo de datos' => 1], [
  'falla_reportada' => 'No arranca: queda en el logo de Asus.', 'accesorios' => 'Cargador',
  'diagnostico' => 'Sistema dañado; el disco está sano.',
], 'pendiente', 2, null, [], 'enviado');
$orden($consolaCamila, ['Limpieza interna' => 1, 'Cambio de pasta térmica' => 1], [
  'falla_reportada' => 'Se apaga después de una hora de juego.', 'accesorios' => 'Joystick y cable HDMI',
  'estado_ingreso' => 'Sin marcas.',
], 'pendiente', 1, $diego);
$orden($tabletJorge, ['Diagnóstico' => 1], [
  'falla_reportada' => 'No enciende.', 'estado_ingreso' => 'Vidrio astillado en el borde.',
], 'pendiente', 0);

// Rechazada por el cliente y cancelada.
$orden($impresoraAna, ['Mantenimiento de impresora' => 1, 'Kit de tintas Epson 664 (4 colores)' => 1], [
  'falla_reportada' => 'No sale la tinta negra.', 'diagnostico' => 'Cabezal tapado; hay que purgar y recargar.',
], 'cancelado', 25, $diego, [], 'rechazado');

// ---------- Turnos ----------

$turnos = $servicio(TurnoService::class);
$habiles = [];
for ($d = 1; count($habiles) < 6; $d++) {
  $fecha = date('Y-m-d', strtotime("+{$d} days"));
  if ((int) date('N', strtotime($fecha)) <= 5) {
    $habiles[] = $fecha;
  }
}
foreach ([
  ['30111222', $notebookAna, 0, '09:00', 'Retira la notebook y consulta por más memoria.', 'confirmado'],
  ['35444555', $notebookValeria, 0, '11:00', 'Cambio de pantalla: dejar el equipo.', 'pendiente'],
  ['40123456', $consolaCamila, 1, '10:00', 'Trae el joystick que no carga.', 'pendiente'],
  ['20555111', $aioRicardo, 1, '15:00', 'Revisar el wifi, se desconecta.', 'confirmado'],
  ['27999000', $monitorLuna, 2, '09:00', 'El monitor parpadea.', 'pendiente'],
  ['42888111', $notebookTomas, 3, '16:00', 'Retira la notebook después de la limpieza.', 'pendiente'],
  ['25888999', $pcCarlos, 4, '10:00', 'Armado de una PC nueva para la oficina.', 'pendiente'],
] as [$dni, $equipoId, $dia, $hora, $descripcion, $estado]) {
  $turnos->guardar(['cliente_id' => $clientes[$dni], 'equipo_id' => $equipoId, 'fecha' => $habiles[$dia], 'hora' => $hora, 'descripcion' => $descripcion, 'estado' => $estado]);
}

// Turnos pasados (no se pueden cargar con fecha pasada desde la interfaz).
foreach ([
  ['38777666', $notebookFlorencia, 2, 'No arranca.', 'realizado'],
  ['22333444', $tabletJorge, 1, 'No enciende.', 'realizado'],
  ['16444777', $notebookGraciela, 9, 'Retiro de la notebook.', 'no_asistio'],
] as [$dni, $equipoId, $dias, $descripcion, $estado]) {
  $db->prepare('INSERT INTO turnos (cliente_id, equipo_id, fecha, hora, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$clientes[$dni], $equipoId, date('Y-m-d', strtotime("-{$dias} days")), '10:00:00', $descripcion, $estado]);
}

echo "Datos de prueba cargados: " . count($clientes) . ' clientes, ' . count($equipos) . " equipos, 14 órdenes y 10 turnos.\n";
if ($admin !== null) {
  echo "Usuarios: admin / demo1234 (administrador) y lucia / demo1234 (empleada).\n";
} else {
  echo "Usuario agregado: lucia / demo1234 (empleada).\n";
}
