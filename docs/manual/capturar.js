// Saca las capturas del manual desde una copia de prueba con los datos de ejemplo (bin/demo.php).
// Uso: BASE=http://127.0.0.1:8099 node docs/manual/capturar.js  (usuario admin / demo1234 de bin/demo.php)
// Requiere playwright-core y Google Chrome (ver docs/manual/README.md).
const { chromium } = require('playwright-core');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const USUARIO = process.env.USUARIO || 'admin';
const CLAVE = process.env.CLAVE || 'demo1234';
const DESTINO = path.join(__dirname, 'capturas');
const CHROME = process.env.CHROME || '/usr/bin/google-chrome';

(async () => {
  require('fs').mkdirSync(DESTINO, { recursive: true });
  const b = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
  const errores = [];

  const sesion = async (opciones = {}) => {
    const ctx = await b.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1.5, colorScheme: 'light', locale: 'es-AR', ...opciones });
    // Sin la versión en el pie: la sube cada entrega y, si no, las capturas quedarían viejas
    // a la semana sin que haya cambiado ninguna pantalla.
    await ctx.addInitScript(() => document.addEventListener('DOMContentLoaded', () => {
      const estilo = document.createElement('style');
      estilo.textContent = '.pie-version { display: none !important; }';
      document.head.append(estilo);
    }));
    const p = await ctx.newPage();
    p.on('pageerror', (e) => errores.push(`${p.url()}: ${e.message}`));
    await p.goto(BASE + '/login');
    await p.evaluate(() => { localStorage.clear(); localStorage.setItem('theme', 'light'); });
    return p;
  };
  const entrar = async (p) => {
    await p.goto(BASE + '/login');
    await p.fill('#usuario', USUARIO); await p.fill('#clave', CLAVE); await p.click('form button[type=submit]');
    await p.waitForLoadState();
  };
  const foto = async (p, nombre, opciones = {}) => {
    await p.mouse.move(0, 0);
    await p.waitForTimeout(350);
    await p.screenshot({ path: path.join(DESTINO, `${nombre}.png`), ...opciones });
    console.log('·', nombre);
  };
  // Solo el contenido de la pantalla (sin el fondo vacío de abajo).
  const contenido = async (p, nombre, extra = 0) => {
    const caja = await p.locator('.app-marco > .card').boundingBox();
    await foto(p, nombre, { clip: { x: 0, y: 0, width: 1280, height: Math.min(caja.y + caja.height + 16 + extra, 2400) }, fullPage: true });
  };
  const ir = async (p, ruta, espera = 300) => { await p.goto(BASE + ruta); await p.waitForTimeout(espera); };
  const tom = async (p, id, texto) => {
    await p.locator(`#${id}-ts-control`).click(); await p.locator(`#${id}-ts-control`).fill(texto);
    await p.waitForTimeout(400); await p.keyboard.press('Enter'); await p.waitForTimeout(300);
  };

  // Una firma de ejemplo en el recuadro (componentes/firma), como la haría el cliente con el dedo.
  const firmar = async (p) => {
    const lienzo = p.locator('.firma-lienzo canvas');
    await lienzo.scrollIntoViewIfNeeded();
    const c = await lienzo.boundingBox();
    await p.mouse.move(c.x + 40, c.y + 140); await p.mouse.down();
    await p.mouse.move(c.x + 120, c.y + 50, { steps: 8 }); await p.mouse.move(c.x + 170, c.y + 130, { steps: 8 });
    await p.mouse.move(c.x + 260, c.y + 60, { steps: 8 }); await p.mouse.move(c.x + 380, c.y + 110, { steps: 8 });
    await p.mouse.up();
  };

  // ---------- 1. Primeros pasos ----------
  let p = await sesion();
  await ir(p, '/login');
  await foto(p, '01-login');
  await entrar(p);
  await ir(p, '/perfil/clave');
  await contenido(p, '02-clave');
  await ir(p, '/primeros-pasos');
  await contenido(p, '04-primeros-pasos');

  // ---------- 2. La pantalla principal ----------
  await ir(p, '/');
  await contenido(p, '03-inicio');
  // Abierto con un clic en la flecha: con hover solo, el desplegable a veces no llega a abrirse.
  await p.locator('.menu-seccion .dropdown-toggle').first().click(); await p.waitForSelector('.menu-seccion .dropdown-menu.show'); await p.waitForTimeout(300);
  await foto(p, '04-menu', { clip: { x: 0, y: 0, width: 1280, height: 330 } });
  await ir(p, '/');
  await p.locator('#busqueda_rapida').pressSequentially('gom', { delay: 60 });
  await p.waitForSelector('#busqueda_sugerencias:not([hidden])');
  await foto(p, '05-sugerencias', { clip: { x: 0, y: 0, width: 1280, height: 340 } });
  await ir(p, '/buscar?q=lenovo');
  await contenido(p, '06-busqueda');
  await ir(p, '/clientes', 1000);
  await contenido(p, '07-listado');
  await ir(p, '/');
  await p.keyboard.press('?'); await p.waitForSelector('.modal.show'); await p.waitForTimeout(400);
  await foto(p, '08-atajos');
  await p.locator('.modal.show .js-modal-aceptar').click(); await p.waitForTimeout(300);
  await ir(p, '/ayuda', 800);
  await foto(p, '06-ayuda', { clip: { x: 0, y: 0, width: 1280, height: 640 } });

  // ---------- 3. Llegó un equipo ----------
  await ir(p, '/recepcion');
  await contenido(p, '10-recepcion-inicio');
  await p.fill('#dni', '34555666'); await p.click('.js-buscar-dni'); await p.waitForSelector('#cliente_nuevo:not([hidden])');
  await p.fill('#nombre', 'Martín'); await p.fill('#apellido', 'Romero'); await p.fill('#telefono', '011 15 4455-6677');
  await p.fill('#email', 'mromero@ejemplo.com.ar');
  await p.selectOption('#tipo', 'notebook');
  await tom(p, 'marca_id', 'Lenovo'); await p.waitForTimeout(500); await tom(p, 'modelo_id', 'ThinkPad');
  await p.fill('#numero_serie', 'PF3XK901');
  await p.fill('#falla_reportada', 'Se apaga sola a los pocos minutos de prenderla');
  await p.locator('#tecnico_id').selectOption({ index: 1 });
  await contenido(p, '11-recepcion-nuevo');
  // Sin el borrador de la 11: si no, la 12 y la 13 salen con «Recuperamos lo que estabas cargando».
  // Primero se sale de la pantalla: el borrador se guarda con una demora y, si no, se vuelve a escribir.
  const sinBorradores = async () => {
    await ir(p, '/');
    await p.evaluate(() => Object.keys(localStorage).filter((k) => k.startsWith('borrador:')).forEach((k) => localStorage.removeItem(k)));
  };
  await sinBorradores();
  // Jorge Medina tiene la tablet en el local (orden abierta).
  await ir(p, '/recepcion');
  await p.fill('#dni', '22333444'); await p.click('.js-buscar-dni'); await p.waitForSelector('#equipos_cliente:not([hidden])');
  await p.locator('#equipos_cliente label', { hasText: 'Tablet' }).click(); await p.waitForSelector('a:has-text("Seguir con la orden")');
  await contenido(p, '12-recepcion-abierta');
  await sinBorradores();
  await ir(p, '/recepcion');
  await p.fill('#dni', '30111222'); await p.click('.js-buscar-dni'); await p.waitForSelector('.js-tras-buscar:not([hidden])');
  await contenido(p, '13-recepcion-conocido');
  // "¿Cómo viene?": accesorios, golpes y la firma del cliente (sin recibir el equipo).
  await p.fill('#accesorios', 'Cargador original y mouse');
  await p.fill('#estado_ingreso', 'Rayón en la tapa; falta la tecla F5');
  await p.click('summary:has-text("firme")'); await p.waitForTimeout(300);
  await firmar(p);
  await p.mouse.move(0, 0); await p.waitForTimeout(350);
  await p.locator('section.paso').last().screenshot({ path: path.join(DESTINO, '14-recepcion-como-viene.png') }); console.log('· 14-recepcion-como-viene');
  await sinBorradores();

  // ---------- 4. Clientes ----------
  await ir(p, '/clientes/crear');
  await contenido(p, '14-cliente-nuevo');
  await p.click('.card-body form button[type=submit]'); await p.waitForTimeout(400);
  await contenido(p, '15-errores');
  await ir(p, '/clientes/1');
  await contenido(p, '16-cliente-ficha');

  // ---------- 5. Equipos ----------
  await ir(p, '/equipos/crear');
  await contenido(p, '17-equipo-nuevo');
  await ir(p, '/equipos/1');
  await contenido(p, '18-equipo-ficha');

  // ---------- 6. Turnos ----------
  await ir(p, '/turnos/crear');
  await tom(p, 'cliente_id', 'GOMEZ'); await p.waitForTimeout(600);
  const proximoHabil = await p.evaluate(() => { const d = new Date(); do { d.setDate(d.getDate() + 1); } while (d.getDay() === 0); return d.toLocaleDateString('en-CA'); });
  await p.fill('#fecha', proximoHabil); await p.locator('#fecha').dispatchEvent('change'); await p.waitForTimeout(800);
  await p.locator('.js-horarios button:not([disabled])').nth(2).click();
  await p.fill('#descripcion', 'Limpieza y cambio de pasta térmica');
  await contenido(p, '20-turno-nuevo');
  await ir(p, '/turnos', 1000);
  await contenido(p, '21-turnos');
  await ir(p, '/turnos/semana');
  await contenido(p, '22-agenda');

  // ---------- 7. Órdenes ----------
  // Órdenes de bin/demo.php: 8 en reparación, 6 y 7 listas sin retirar, 11 a 13 recibidas, 1 entregada.
  await ir(p, '/ordenes/13');
  await contenido(p, '30-orden-recibida');
  await ir(p, '/ordenes/13/editar');
  await tom(p, 'servicio_id', 'Limpieza'); await p.keyboard.press('Escape');
  await tom(p, 'repuesto_id', 'Pasta'); await p.keyboard.press('Escape');
  await p.fill('input[name^="cantidad_repuesto"]', '2'); await p.locator('input[name^="cantidad_repuesto"]').blur();
  await contenido(p, '31-orden-editar');
  // Los modos de un repuesto (sin guardar): la pantalla de la orden 8, a costo.
  await ir(p, '/ordenes/8/editar');
  await p.locator('#detalle_orden tr[data-tipo="repuesto"]', { hasText: 'Pantalla' }).locator('.js-modo').selectOption('costo');
  await p.mouse.move(0, 0); await p.waitForTimeout(350);
  await p.locator('#detalle_orden').locator('xpath=..').screenshot({ path: path.join(DESTINO, '39-repuestos-modos.png') }); console.log('· 39-repuestos-modos');
  await ir(p, '/ordenes/8');
  await contenido(p, '32-orden-reparacion');
  await p.click('button:has-text("Terminar el trabajo")'); await p.waitForSelector('.modal.show'); await p.waitForTimeout(400);
  await foto(p, '33-terminar');
  await p.locator('.modal.show .js-modal-cancelar').click(); await p.waitForTimeout(300);
  await ir(p, '/ordenes/12');
  await p.click('button:has-text("Empezar el trabajo")'); await p.waitForLoadState(); await p.waitForTimeout(400);
  await foto(p, '34-deshacer', { clip: { x: 640, y: 500, width: 640, height: 300 } });
  await p.click('.toast button:has-text("Deshacer")'); await p.waitForLoadState();
  await ir(p, '/ordenes/7');
  await contenido(p, '35-orden-lista');
  await p.click('button:has-text("Imprimir / Enviar")'); await p.waitForTimeout(300);
  await foto(p, '36-imprimir', { clip: { x: 0, y: 0, width: 1280, height: 720 } });
  await ir(p, '/ordenes', 1200);
  await contenido(p, '37-ordenes');
  await ir(p, '/ordenes/tablero', 600);
  await contenido(p, '35-tablero');
  await ir(p, '/ordenes/6/entregar');
  await firmar(p);
  await contenido(p, '34-entregar');

  // ---------- 8. Presupuestos y comprobantes ----------
  await ir(p, '/ordenes/8/presupuesto');
  await foto(p, '40-presupuesto', { fullPage: true });
  await ir(p, '/ordenes/1/entrega');
  await foto(p, '41-entrega', { fullPage: true });
  await ir(p, '/ordenes/8/ticket/recepcion');
  await p.locator('.ticket').screenshot({ path: path.join(DESTINO, '42-ticket.png') }); console.log('· 42-ticket');

  // ---------- 9. Pagos y deudores ----------
  await ir(p, '/ordenes/7#registrar_pago', 800);
  await foto(p, '42-cobro');
  await ir(p, '/deudores', 800);
  await contenido(p, '43-deudores');

  // ---------- 10. Catálogos ----------
  await ir(p, '/servicios', 800);
  await contenido(p, '50-servicios');
  await ir(p, '/combos/1/editar');
  await contenido(p, '51-combo');
  await ir(p, '/modelos', 800);
  await contenido(p, '52-modelos');

  // ---------- 11. Stock ----------
  await ir(p, '/repuestos', 800);
  await contenido(p, '60-repuestos');
  await ir(p, '/repuestos/5/stock', 800);
  await p.locator('details.mas-datos summary').click();
  await contenido(p, '61-stock');
  await ir(p, '/proveedores/1/editar');
  await contenido(p, '62-proveedor');

  // ---------- 12 y 13. Precios y reportes ----------
  await ir(p, '/precios');
  await p.fill('#porcentaje', '10');
  await p.click('button:has-text("Ver cambios")'); await p.waitForLoadState(); await p.waitForTimeout(500);
  await contenido(p, '70-precios');
  await ir(p, '/reportes');
  await contenido(p, '71-reportes');

  // ---------- 15. Configuración ----------
  for (const seccion of ['negocio', 'trabajo', 'turnos', 'notificaciones', 'mensajes', 'portal', 'backups']) {
    await ir(p, `/configuracion/${seccion}`);
    await contenido(p, `80-config-${seccion}`);
  }

  // ---------- 16 y 17. Usuarios, empleados, auditoría, registro ----------
  await ir(p, '/usuarios'); await p.waitForTimeout(600);
  await contenido(p, '90-usuarios');
  await ir(p, '/usuarios/crear');
  await contenido(p, '91-usuario-nuevo');
  await ir(p, '/empleados'); await p.waitForTimeout(600);
  await contenido(p, '92-empleados');
  await ir(p, '/auditoria', 1000);
  await contenido(p, '93-auditoria');
  await ir(p, '/logs', 600);
  await contenido(p, '94-logs');

  // Borrador recuperado.
  await ir(p, '/clientes/crear');
  await p.fill('#nombre', 'Laura'); await p.fill('#apellido', 'Medina'); await p.waitForTimeout(800);
  await p.reload(); await p.waitForTimeout(700);
  await foto(p, '19-borrador', { clip: { x: 0, y: 0, width: 1280, height: 520 } });
  await p.click('button:has-text("Descartar")'); await p.waitForTimeout(400);

  // ---------- 14. Lo que ve el cliente ----------
  const cliente = await b.newContext({ viewport: { width: 420, height: 860 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, colorScheme: 'light' });
  const pc = await cliente.newPage();
  await pc.goto(BASE + '/seguimiento');
  await pc.screenshot({ path: path.join(DESTINO, '95-portal.png') }); console.log('· 95-portal');
  await pc.fill('#dni', '22333444'); await pc.fill('#orden', '13'); await pc.click('form button[type=submit]'); await pc.waitForLoadState();
  await pc.screenshot({ path: path.join(DESTINO, '96-portal-resultado.png') }); console.log('· 96-portal-resultado');
  await pc.goto(BASE + '/presupuesto/' + 'e'.repeat(64)); // el link fijo de la orden 11, sin responder (bin/demo.php)
  await pc.screenshot({ path: path.join(DESTINO, '97-presupuesto-cliente.png'), fullPage: true }); console.log('· 97-presupuesto-cliente');

  // ---------- Celular ----------
  const m = await sesion({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await entrar(m);
  await ir(m, '/');
  await foto(m, '09-celular-inicio');
  await m.locator('.barra-inferior button').click(); await m.waitForTimeout(500);
  await foto(m, '09-celular-menu');
  await ir(m, '/ordenes/8');
  await foto(m, '38-celular-orden');

  // ---------- Modo mostrador: habilitar este navegador, ponerse un PIN y salir ----------
  await ir(p, '/mostrador');
  await p.fill('#nombre', 'PC del mostrador'); await p.click('button:has-text("Usar este equipo")'); await p.waitForLoadState();
  await contenido(p, '93-mostrador');
  await ir(p, '/perfil/clave');
  await p.fill('#pin_nuevo', '4826'); await p.fill('#pin_confirmacion', '4826'); await p.fill('#clave_actual_pin', CLAVE);
  await p.click('button:has-text("Guardar el PIN")'); await p.waitForLoadState();
  await p.click('header button:has-text("Salir")'); await p.waitForLoadState();
  await p.click('.toast .btn-close').catch(() => {});
  await p.locator('.usuarios-pin label').first().click(); await p.waitForTimeout(300);
  await foto(p, '02-mostrador', { clip: { x: 340, y: 0, width: 600, height: 760 } });

  console.log(errores.length ? 'ERRORES JS:\n' + errores.join('\n') : 'Sin errores de JS.');
  await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
