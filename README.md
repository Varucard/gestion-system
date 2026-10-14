# Gestión de servicio técnico de PC

Aplicación web en PHP para administrar un servicio técnico de computadoras: notebooks, PC de
escritorio, all-in-one, monitores, impresoras, celulares, tablets y consolas.

Nació como copia del sistema de gestión de un taller mecánico, adaptado al rubro: el vehículo pasó a
ser el **equipo**, el mecánico el **técnico**, la patente el **número de serie** y el service por
kilometraje el **mantenimiento preventivo** por fecha.

## Funcionalidades

- **"Llegó un equipo"**: con el DNI del cliente se ven sus equipos; si es nuevo, el cliente y el
  equipo se cargan en la misma pantalla y queda la orden abierta, con la falla, los accesorios, el
  estado físico, **fotos con la cámara del celular** y la **firma del cliente** en la pantalla. Si el
  equipo ya está en el local, lo principal es seguir con esa orden (abrir otra se confirma).
- **Tablero del local** (`/ordenes/tablero`): Recibido · En reparación · Listo, con tarjetas que se
  arrastran (también en el celular) o se mueven con «Mover a…».
- **Entrega del equipo** como paso propio, con firma de conformidad; la garantía corre desde la
  entrega y se puede deshacer.
- **"Para no olvidarse"** en el inicio: equipos listos sin retirar, presupuestos sin respuesta y
  mantenimientos por vencer, cada uno con su **WhatsApp con link** ya armado.
- **Modo mostrador**: en la PC compartida, cada uno entra con un PIN de 4 dígitos.
- **Tickets para impresora térmica** (58/80 mm) y **QR** al seguimiento en tickets y comprobantes.
- **Ayuda dentro del sistema**: el manual, con un botón en cada pantalla que abre la parte que la
  explica. **Primeros pasos** guiados y **encuesta de usabilidad** (SUS) por rondas.
- **Repuestos a costo y repuestos que trae el cliente** en la orden.
- **Clientes** con ficha (foto, equipos, historial de órdenes y turnos, deuda) y acceso directo a WhatsApp.
- **Equipos** con tipo, marca, modelo, número de serie, procesador, memoria, almacenamiento, color,
  observaciones, galería de imágenes e historial.
- **Órdenes de reparación** con cantidades y precios por ítem, combos, técnico asignado, falla
  reportada, accesorios recibidos, estado físico al ingresar, diagnóstico, trabajo realizado, notas
  internas y próximo mantenimiento; presupuesto y comprobante de entrega
  (HTML imprimible y PDF). El presupuesto se **envía por email** y el cliente lo **acepta online**; si se
  modifica uno ya aceptado, se le avisa para que lo vuelva a aceptar. Al finalizar la orden, el cliente
  recibe el aviso de **equipo listo** con el saldo a abonar.
- **Pagos** parciales por orden, saldos y listado de **deudores**.
- **Stock de repuestos y componentes** (discos, memorias, fuentes, pantallas, cargadores…): precio de costo y margen, ingresos por proveedor, ajustes por conteo, descuento
  automático al finalizar órdenes, historial de movimientos y alerta de stock mínimo. **Proveedores**.
- **Actualización masiva de precios** por porcentaje, con redondeo y vista previa.
- **Turnos** según el horario de atención y los feriados, con cupos simultáneos configurables.
  Al agendar, el cliente recibe un **email para confirmar o cancelar** y, el día hábil anterior,
  un **recordatorio automático**.
- **Portal "Seguí tu equipo"** (`/seguimiento`): el cliente consulta con su DNI y el número de una de
  sus órdenes (figura en el comprobante y en el presupuesto) el estado de sus trabajos y sus próximos
  turnos, sin necesidad de usuario. Desde el link del presupuesto entra directo, sin DNI ni número.
- **Todo configurable** desde *Configuración > Sistema*: datos del negocio, presupuestos, horario,
  feriados, textos de los mensajes, canales de aviso, stock y portal.
- **Panel de inicio** con la actividad del día, **agenda semanal** con cupos libres y **búsqueda rápida**.
- **Reportes** (cobranzas, más vendidos, por técnico, stock valorizado) con exportación a Excel.
- **Aviso automático de próximo mantenimiento** (limpieza, pasta térmica) al cliente.
- **Auditoría** (quién hizo qué y cuándo) y **registro técnico** (logs) con visor.
- **Backup automático diario** de la base y las imágenes.
- **Usuarios** con roles (administrador / empleado) y **empleados** del negocio.
- **App instalable (PWA)** en el celular o la PC, con barra de navegación inferior en pantallas chicas,
  tablas que pliegan sus columnas y aviso de "Sin conexión".

## Diseño

La interfaz usa la identidad de **PC Fighter** (la misma del sitio web): fondo azul noche con las
líneas de neón, violeta, magenta, azul y cian, botones con el degradado de la marca, títulos en
*Jersey Sharp* y textos en *Saira Semi Condensed* (servidas desde `public/assets/fonts/`, sin CDN).
El modo oscuro es el de la marca y es el que se ve por defecto; el claro usa la misma paleta sobre
blanco. Cada sección conserva su color (Órdenes violeta, Turnos magenta, Clientes verde azulado,
Equipos azul, Stock cobre, Configuración lavanda). Todos los colores están en
`public/assets/css/tokens.css`.

## Stack

| Componente | Versión |
| --- | --- |
| PHP + Apache | 8.4 (compatible con 8.2+) |
| MySQL | 8.4 LTS |
| phpMyAdmin | 5 |
| Dompdf (vía Composer) | 3.1 |
| Bootstrap · jQuery · DataTables (+ Responsive) · Tom Select · SortableJS | 5.3.8 · 3.7.1 · 2.3.8 (3.0.8) · 2.6.2 · 1.15.6 |
| chillerlan/php-qrcode | 5 |
| Symfony Mailer | 7.4 |
| PHPUnit | 11.5 |

## Puesta en marcha

```bash
cp .env.example .env        # completar las claves
docker compose up -d --build
```

- Aplicación: <http://localhost:8060> (puertos configurables en `.env`)
- phpMyAdmin: <http://localhost:8061> (solo desde el propio servidor; MySQL también
  escucha solo en `127.0.0.1`. Para abrirlos a la red: `PMA_BIND` / `DB_BIND` en `.env`)

La imagen se construye para producción (`php.ini` de producción, sin Xdebug). Si el
sistema queda detrás de un proxy (nginx, Cloudflare), indicar sus IPs en
`TRUSTED_PROXIES` para que los bloqueos por intentos fallidos y la auditoría usen la
IP real del cliente.

Al iniciar, el contenedor `public` instala las dependencias (si faltan) y aplica
las migraciones pendientes. **La primera vez que entrás, la aplicación pide crear
el usuario administrador.** Después, en *Configuración > Negocio*, cargar el nombre, CUIT,
dirección y contacto del negocio (salen en los presupuestos, los emails y el portal), y reemplazar
el logo genérico de `public/assets/img/` (`logo.png`, `logo_64.png` e `icono-*.png`) por el propio.

Si se olvida la contraseña del administrador:

```bash
docker compose exec public php bin/usuario.php clave <usuario>
```

### Emails a clientes (confirmación y recordatorio de turnos)

Completar en `.env` el servidor SMTP y la dirección pública del sistema (se usa en
los links de los emails). Por ejemplo, con Gmail y una
[contraseña de aplicación](https://myaccount.google.com/apppasswords):

```
APP_URL=https://turnos.miservicio.com.ar
MAIL_DSN=smtp://usuario%40gmail.com:CLAVE_DE_APLICACION@smtp.gmail.com:587
MAIL_FROM=usuario@gmail.com
```

Sin `MAIL_DSN` no se envían emails (el resto funciona igual). El estado de cada
canal y el registro de avisos enviados están en *Configuración > Avisos*.

### Tareas automáticas

El servicio `tareas` de Docker ejecuta `bin/tareas.php` cada 15 minutos: envía los
recordatorios de turnos del día hábil siguiente y los avisos de próximo mantenimiento
(solo en días hábiles, dentro del horario de atención y desde la hora configurada),
genera el backup diario y limpia registros y logs viejos. Sin Docker, agregar
al crontab: `*/15 * * * * cd /ruta/al/proyecto && php bin/tareas.php`.

### WhatsApp

WhatsApp va a mano: los botones abren la conversación (wa.me, WhatsApp Web o la app de
escritorio, según *Configuración > Avisos*) con el mensaje y el link ya escritos (presupuesto
para aceptar, equipo listo con el detalle del trabajo, turno para confirmar, mantenimiento), y la
persona lo envía desde su teléfono. La API de WhatsApp Business se descartó: cobra por mensaje y
no suma frente al botón con link.

### API de seguimiento (para el sitio web)

`POST /api/v1/seguimiento` devuelve en JSON el estado de **un trabajo**, con el número de ticket
(el de la orden: `0013`, `#13` o `PCF-0013`) y el DNI del cliente. La usa la sección «Seguí tu
servicio» del sitio de PC Fighter, que la llama **desde su servidor** (la clave no llega al
navegador). Se activa con `SEGUIMIENTO_API_KEY` en `.env` (sin clave, la ruta no existe) y respeta
*Configuración > Portal* (habilitado y si se muestran los montos).

```bash
curl -X POST https://sistema.ejemplo.com/api/v1/seguimiento \
  -H "X-Api-Key: $SEGUIMIENTO_API_KEY" -H "X-Cliente-IP: 200.1.2.3" \
  -H "Content-Type: application/json" -d '{"ticket": "PCF-0013", "dni": "22333444"}'
```

| Respuesta | Cuándo |
| --- | --- |
| `200` | `ticket`, `cliente` (nombre de pila), `equipo`, `ingreso`, `etapa` y `etapas` (recibido → en reparación → listo → entregado, o cancelado), `terminado`, `entregado`, `falla`, `trabajos`, `presupuesto` (`estado` y, si espera respuesta, el `link` para aceptarlo), `montos` (o `null`) y `portal` |
| `422` | Datos incompletos (`campos` dice cuáles) o ticket y DNI que no coinciden (mismo mensaje si el ticket no existe o es de otro) |
| `429` | Demasiadas consultas fallidas: el mismo bloqueo del portal (15 minutos tras 10), por la IP de `X-Cliente-IP` |
| `401` / `404` | Clave inválida / API sin clave o portal deshabilitado |

Nunca devuelve datos de contacto, notas internas ni técnicos asignados.

### Sin Docker

Requiere PHP 8.2+ con `pdo_mysql`, `gd`, `mbstring` y `dom`, más un MySQL accesible.

```bash
composer install
cp .env.example .env        # DB_HOST=127.0.0.1, etc.
composer migrate
php -S localhost:8000 -t public public/index.php
```

### En la misma VPS que Mecánica Abel

En producción este sistema corre en la VPS de [Mecánica Abel](https://github.com/Varucard/abel_mecanica)
y **usa su MySQL** en vez de levantar uno propio: MySQL es lo que más memoria ocupa (~500 MB) y
la VPS es chica. Tiene su propia base y su propio usuario dentro de ese servidor, y sus propios
contenedores de aplicación (`gestion` y `gestion-tareas`, ~25 MB), con la misma imagen que Abel.
No comparte el contenedor `public` de Abel: las dos aplicaciones leen las mismas variables
(`DB_DATABASE`, `MAIL_DSN`, `APP_URL`…) y en un mismo Apache se pisarían. La clave de root de
MySQL no llega a nuestros contenedores: el override la pisa vacía.

Todo se maneja desde la carpeta de Abel: **el `docker-compose.yml` de este repo no se usa ahí.**

```mermaid
flowchart LR
  AP[Apache del host] -->|taller.pcfighter.com.ar| PUB[public de Abel]
  AP -->|www.pcfighter.com.ar| PCF[pcfighter<br/>sitio de PC Fighter]
  AP -->|gestion.pcfighter.com.ar<br/>127.0.0.1:8060| GES[gestion]
  PCF -->|red pcfighter<br/>Seguí tu servicio| GES
  PUB --> DB[(database de Abel<br/>taller_mecanico + servicio_tecnico)]
  GES --> DB
  GT[gestion-tareas] --> DB
```

1. Clonar este repo **al lado** de `abel_mecanica/` (en la VPS, `/opt/abel/gestion-system`).
2. `.env` desde `.env.example` con `chmod 600`, y estas diferencias:

   | Variable | Valor |
   | --- | --- |
   | `DB_HOST` | `database` (el contenedor de MySQL de Abel) |
   | `DB_DATABASE`, `DB_USERNAME` | `servicio_tecnico`, `gestion` (distintos de los de Abel) |
   | `DB_PASSWORD` | Generada con `openssl rand -hex 24` |
   | `APP_URL` | `https://gestion.pcfighter.com.ar` |
   | `TRUSTED_PROXIES` | La IP del gateway de la red de Docker, `/32` (ver *Proxy de Apache* en `DESPLIEGUE.md` de Abel); no todo `172.16.0.0/12`, que incluye a los otros contenedores |
   | `SEGUIMIENTO_API_KEY` | Generada con `openssl rand -hex 32`; la misma va en `GESTION_API_KEY` del sitio |

   `MYSQL_ROOT_PASSWORD`, los puertos y los `*_BIND` no se usan: manda el `.env` de Abel.
3. Crear la base y el usuario en el MySQL de Abel, una sola vez (desde `abel_mecanica/`, con la
   contraseña del paso 2):

   ```bash
   docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot' <<'SQL'
   CREATE DATABASE servicio_tecnico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'gestion'@'%' IDENTIFIED BY 'LA_DB_PASSWORD_DEL_PASO_2';
   GRANT ALL PRIVILEGES ON servicio_tecnico.* TO 'gestion'@'%';
   SQL
   ```

4. Sumar los servicios de `docker-compose.override.ejemplo.yml` al final del
   `docker-compose.override.yml` de Abel, y levantarlos:
   `docker compose up -d --build gestion gestion-tareas`. Al arrancar, `gestion` instala las
   dependencias (sin las de desarrollo, con `ENTORNO=produccion`) y aplica las migraciones en su
   base, igual que con su propio compose. `gestion` se suma a la red `pcfighter`, que declara el
   override del sitio de PC Fighter; si el sitio no corre ahí, sacarla. Con los dos, el override
   se arma así, desde `abel_mecanica/`:

   ```bash
   { cat ../pc-fighter/docker-compose.override.ejemplo.yml
     sed -n '/^services:/,$p' ../gestion-system/docker-compose.override.ejemplo.yml | tail -n +2
   } > docker-compose.override.yml
   ```
5. En el Apache del host, un vhost como el del taller para `gestion.pcfighter.com.ar` hacia
   `127.0.0.1:8060`, y `certbot --apache`.
6. En el sitio de PC Fighter, `GESTION_URL=http://gestion` (por la red interna `pcfighter`, sin
   salir del servidor) y `GESTION_API_KEY` con la clave del paso 2. La API solo la usa el sitio:
   en el vhost del host, `/api/` va cerrado desde internet (`<Location /api/> Require all denied`).

**Operación**, siempre desde `abel_mecanica/`:

```bash
cd ../gestion-system && git pull && cd ../abel_mecanica
docker compose up -d gestion gestion-tareas         # aplica migraciones y dependencias nuevas
docker compose exec gestion php bin/usuario.php clave <usuario>
docker compose logs gestion --tail 50
```

El phpMyAdmin de Abel ve las dos bases; con el usuario `gestion` solo se ve la de este sistema.
Los scripts de backup, en este modo, van con `ABEL_DIR` (ver [Backups](#backups)). Root de
MySQL queda solo para `localhost` (ver `DESPLIEGUE.md` de Abel): el phpMyAdmin se usa con el
usuario de cada sistema.

## Arquitectura

Patrón MVC liviano, sin framework, con separación estricta de responsabilidades:

```
app/
├── Core/           Infraestructura: App, Router, Container (autowiring), Request,
│                   View, Session (flash + CSRF), Auth, Logger, Migrator, Database, Env
├── Controllers/    Reciben la petición, llaman al servicio y renderizan. Sin SQL ni reglas.
├── Services/       Lógica de negocio y validaciones (precios congelados, disponibilidad
│                   de turnos, bajas protegidas, configuración, PDF).
├── Repositories/   Único lugar con SQL (PDO + sentencias preparadas).
├── Models/         Entidades del dominio (Persona → Cliente, Equipo, Orden, Turno…).
├── Enums/          Estados de órdenes, turnos y clientes/equipos; tipos de equipo.
├── Notificaciones/ Canales de aviso automático (email).
├── Support/        Validator, ImageUpload, HorarioAtencion, ConsultaPaginada (DataTables en el servidor).
└── helpers.php     Funciones para vistas: e(), url(), asset(), money(), csrf_field()…
bin/                migrate.php (migraciones), tareas.php (recordatorios) y usuario.php (recuperar acceso).
config/
├── routes.php      Todas las rutas de la aplicación y su nivel de acceso.
└── negocio.php     Valores por defecto de la configuración (y variables de los mensajes).
views/              Templates PHP: layouts/, partials/, componentes/ (campo, estado) y una carpeta por módulo.
public/             Única carpeta expuesta por Apache: index.php, sw.js, offline.html y assets/
                    (css/ por capas, js/, vendor/ con las librerías servidas localmente).
database/
└── migrations/     NNNN_*.sql, se aplican en orden y quedan registradas en `migraciones`.
scripts/            backup.sh y restore.sh.
storage/            Configuración, imágenes subidas y logs (no versionado).
tests/              Unit/ (sin base) e Integration/ (contra MySQL).
```

Flujo de una petición: `public/index.php` → `App` → `Router` (control de acceso)
→ `Controller` → `Service` → `Repository` → vista.

Para cambiar la base de datos se agrega un archivo nuevo en `database/migrations/`
(por ejemplo `0002_descripcion.sql`); nunca se modifica uno ya aplicado.

### Rutas principales

| Módulo | Rutas |
| --- | --- |
| Clientes | `/clientes`, `/clientes/crear`, `/clientes/{id}/editar` |
| Equipos | `/equipos`, `/equipos/crear`, `/equipos/{id}/editar` |
| Órdenes | `/ordenes`, `/ordenes/crear`, `/ordenes/{id}/editar`, `/ordenes/{id}/presupuesto`, `/ordenes/{id}/presupuesto/pdf` |
| Turnos | `/turnos`, `/turnos/crear`, `/turnos/{id}/editar` |
| Catálogos | `/marcas`, `/modelos`, `/servicios`, `/repuestos` |
| Inicio | `/` (panel) |
| Clientes | `/clientes/{id}` (ficha), `/deudores` |
| Equipos | `/equipos/{id}` (ficha con imágenes) |
| Órdenes | `/ordenes/{id}` (ficha con pagos), `/ordenes/{id}/entrega`, `/ordenes/{id}/entrega/pdf` |
| Stock | `/repuestos/{id}/stock`, `/proveedores` |
| Acceso | `/login`, `/perfil/clave` |
| API (sitio web) | `POST /api/v1/seguimiento` (con `SEGUIMIENTO_API_KEY`) |
| Públicas (clientes) | `/seguimiento`, `/turno/{token}` (confirmar o cancelar), `/presupuesto/{token}` (aceptar o rechazar) |
| Búsqueda y agenda | `/buscar?q=`, `/turnos/semana` |
| Solo administradores | `/configuracion/{seccion}`, `/usuarios`, `/empleados`, `/precios`, `/reportes`, `/auditoria`, `/logs` |

Todas las acciones que modifican datos (alta, edición, baja, cambio de estado)
son `POST` con token CSRF.

### Interfaz

- **Colores en un solo lugar**: `public/assets/css/tokens.css` define cada color con su versión
  clara y oscura. Bootstrap y las librerías leen de ahí, así que el modo oscuro (nativo de
  Bootstrap, `data-bs-theme` en `<html>`) funciona en cualquier componente sin reglas extra.
- **CSS por capas** (importadas en `views/partials/head.php`, cada una con su versión): vendor → tokens → base → componentes → librerías → páginas →
  utilidades. Una capa posterior siempre le gana a una anterior: no hace falta `!important`.
  Nada de `style="…"` en las vistas: para un ancho puntual hay clases en `utilidades.css`.
- **Componentes de vista**: `componentes/campo` (etiqueta + control + ayuda, con `old()`;
  admite prefijo `$`, desplegable con buscador y múltiple), `componentes/estado` (badge de
  orden o turno) y `componentes/vacio` (estado vacío). Helpers: `importe()` para montos,
  `icono()` (Bootstrap Icons) y `boton_accion()` para las acciones de fila. El menú vive en
  `App\Support\MenuPrincipal` y se dibuja como botones en la PC y como barra inferior +
  menú lateral en el celular.
- **Botones según su función**: la acción principal de la pantalla va en el color de la
  sección (`btn-seccion`); ver y editar, con contorno; lo destructivo, en rojo. Dentro de
  las tablas y en el celular, las acciones de fila muestran solo el ícono.
- **Confirmaciones y avisos**: `data-confirm="¿…?"` en un formulario abre un modal (rojo si
  borra, anula o desactiva); desde JS, `window.confirmar()` y `window.avisar()`. Los
  mensajes de éxito son avisos flotantes; los errores quedan en la página.
- **Contraste**: cada color de fondo tiene su color de texto en `tokens.css`, elegido para
  llegar a 4.5:1.
- **Librerías locales** en `public/assets/vendor/` (sin CDN): la app instalada abre aunque la
  conexión sea mala. `asset()` agrega la fecha del archivo a la URL, así un cambio nunca queda
  tapado por el caché. Al cambiar `public/sw.js`, subir su `VERSION`.

## Reglas de negocio

- **Clientes**: el DNI no se modifica al editar. Un cliente con equipos o turnos
  no se elimina (se desactiva).
- **Equipos**: tipo, marca y modelo obligatorios. El número de serie es opcional, pero si se carga
  no puede repetirse. Se activan/desactivan en lugar de borrarse.
- **Órdenes**: se pueden abrir sin ítems (el equipo entra a diagnóstico), pero no terminarse. Cada ítem tiene cantidad y precio unitario;
  el precio sugerido es el del catálogo y queda congelado en la orden. Solo se
  editan órdenes pendientes o en proceso, y el total no puede quedar por debajo
  de lo pagado. Al finalizar se registra la fecha y se descuenta el stock de los
  repuestos (se repone si la orden se reabre o cancela).
- **Pagos**: parciales, sin superar el saldo, con las formas de pago configuradas.
  Un cliente es deudor si tiene órdenes finalizadas con saldo. Anular un pago es
  solo para administradores.
- **Turnos**: el equipo debe pertenecer al cliente; no se agenda en fechas
  pasadas, fuera del horario de atención, en feriados ni por encima de los cupos
  simultáneos (los cancelados y ausentes liberan el lugar). Si se reprograma, se
  vuelve a pedir la confirmación al cliente.
- **Portal**: por defecto pide DNI y número de una orden del cliente (con solo el DNI
  cualquiera podría ver datos ajenos; se puede cambiar en Configuración). No muestra datos
  de contacto, da el mismo error si el DNI no existe o la orden no es suya, y bloquea por
  15 minutos tras 10 consultas fallidas.
- **Catálogos**: no se puede borrar una marca, modelo, servicio, repuesto o
  proveedor en uso.
- **Seguridad**: contraseñas con `password_hash`, bloqueo de 15 minutos tras 5
  intentos fallidos, cierre de sesión por 8 h de inactividad, CSRF en todos los
  formularios y siempre al menos un administrador activo.
- **Imágenes**: JPG/PNG/WEBP hasta 8 MB, guardadas fuera de `public/` y
  re-codificadas (se eliminan metadatos como la ubicación GPS).

## Backups

El backup diario es automático (servicio `tareas`): a partir de la hora configurada
en *Configuración > Backups* guarda en `storage/backups` la base de datos
(`db_*.sql.gz`) y las imágenes y configuración (`archivos_*.tar.gz`), conservando los
últimos N. Desde esa misma pantalla se puede generar uno a mano y descargarlo. El
volcado se hace desde una foto consistente de la base, aunque se esté usando el sistema.

**Copia fuera del servidor:** con `BACKUP_COPIA_DIR` cada backup se copia además a otra
carpeta (un disco externo o una carpeta sincronizada con la nube), que hay que montar en
los contenedores, por ejemplo con un `docker-compose.override.yml`:

```yaml
services:
  public: { volumes: ["/mnt/disco_externo/backups_servicio:/backups_externos"] }
  tareas: { volumes: ["/mnt/disco_externo/backups_servicio:/backups_externos"] }
```

y `BACKUP_COPIA_DIR=/backups_externos` en `.env`. Si la copia falla, queda en el registro
del sistema y el backup local se conserva igual.

**Cifrado:** con `BACKUP_CLAVE_PUBLICA` en `.env`, cada backup (el automático, el manual y el de
`scripts/backup.sh`) se guarda cifrado como `.cif`, y el original se borra. El servidor solo tiene la
clave **pública**: puede cifrar pero no descifrar. Una copia filtrada, o el disco del servidor, no se
leen sin la clave **privada**, que se guarda fuera (un gestor de contraseñas). **Sin la clave privada
los backups no se pueden recuperar.**

```bash
php bin/backup.php claves                                  # en una PC propia: genera el par
php bin/backup.php descifrar db_<fecha>.sql.gz.cif db.sql.gz   # pide la clave privada, sin eco
```

`scripts/restore.sh` acepta los `.cif` directamente: pide la clave privada (o la toma de
`BACKUP_CLAVE_PRIVADA`) y descifra en el contenedor, a una carpeta temporal que se borra al terminar.

También se puede hacer desde la consola del servidor:

```bash
scripts/backup.sh                       # en backups/ (usa mysqldump del contenedor de la base)
scripts/restore.sh storage/backups/db_<fecha>.sql.gz storage/backups/archivos_<fecha>.tar.gz
```

Los dos usan siempre la base de `DB_DATABASE` y entran con el usuario de la aplicación
(`DB_USERNAME` / `DB_PASSWORD` del `.env`), no con root: la clave viaja por el entorno y no
aparece en la lista de procesos. En la VPS compartida con Mecánica Abel se corren con
`ABEL_DIR=../abel_mecanica` adelante, para que usen su MySQL; nuestro usuario no ve la base del
taller. Los backups se guardan solo para el dueño (`umask 077`). El restore revisa el archivo de
archivos **antes** de tocar la base: rechaza enlaces y restaura solo `storage/config` y
`storage/uploads`, dentro del contenedor y con `www-data` como dueño.

## Logs y auditoría

- **Auditoría** (*Configuración > Auditoría*): acciones del negocio con usuario, IP y detalle
  (cambios de estado, pagos, stock, precios, configuración, ingresos al sistema). También se ve
  en la ficha de cada orden.
- **Registro del sistema** (*Configuración > Registro del sistema*): archivos diarios
  `storage/logs/app-AAAA-MM-DD.log` (una línea JSON por evento) con errores, avisos de PHP y
  eventos. Cada petición tiene un id (`X-Request-Id`) que aparece en la página de error, para
  encontrarla en el registro. Nivel y días a conservar: `LOG_LEVEL` y `LOG_DIAS` en `.env`.

## Manual de usuario

El manual está dentro del sistema (**Ayuda**, `/ayuda`) y en
[`docs/Manual_de_Usuario_Servicio_Tecnico_PC.pdf`](docs/Manual_de_Usuario_Servicio_Tecnico_PC.pdf).
La fuente es `docs/manual/manual.html`, con las capturas en `docs/manual/capturas/`. Cómo
rehacer las capturas y el PDF: [`docs/manual/README.md`](docs/manual/README.md).

## Datos de prueba

Para probar el sistema o sacar capturas, `bin/demo.php` carga datos ficticios: técnicos,
catálogo de servicios y repuestos con stock, clientes con sus equipos, órdenes en todos los
estados (con pagos, deudas y presupuestos respondidos), turnos y mantenimientos por vencer.
Solo corre sobre una base **sin clientes**, así que nunca se mezcla con datos reales.

```bash
docker compose exec public php bin/demo.php
```

Si la base no tiene usuarios, crea `admin` (administrador); en todos los casos agrega `lucia`
(empleada). Ambos con la contraseña `demo1234`.

## Desarrollo

```bash
docker compose exec public composer lint     # sintaxis
docker compose exec public composer migrate  # aplicar migraciones
docker compose exec -e DB_TEST_HOST=database -e DB_TEST_PASSWORD="$MYSQL_ROOT_PASSWORD" public composer test
```

Los tests de integración usan una base descartable (`gestion_test`) y se omiten si
no está definida `DB_TEST_HOST`. En GitHub Actions corren en cada push y PR.

La hoja de ruta con lo hecho y lo pendiente está en [ROADMAP.md](ROADMAP.md).

La versión está en el archivo `VERSION` (sale en el pie, la Ayuda y el PDF del manual). No se
edita a mano: el workflow `.github/workflows/version.yml` la sube al mergear cada PR a `main`
(la del medio por defecto; con las etiquetas `version:patch`, `version:major` o `version:none`).

Para depurar con Xdebug: `ENTORNO=desarrollo` y `XDEBUG_MODE=debug` en `.env`,
reconstruir (`docker compose up -d --build`) y usar la configuración de
`.vscode/launch.json` (puerto 9004).

Si una migración falla a la mitad, el error indica en qué sentencia; una vez corregida,
`composer migrate` continúa desde esa sentencia (las anteriores ya quedaron aplicadas).

Con `APP_DEBUG=true` se muestran los mensajes de error; en producción dejarlo en
`false` (los errores quedan en `storage/logs/app.log`).
