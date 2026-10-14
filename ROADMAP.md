# Hoja de ruta

Estado: ✅ hecho · 🚧 en curso · ⏳ pendiente · 💭 a futuro (requiere definiciones)

## Punto de partida

El sistema parte del de gestión de un taller mecánico, que ya traía clientes, órdenes con
presupuesto y aceptación online, pagos y deudores, stock y proveedores, turnos con confirmación y
recordatorio, portal de seguimiento, reportes, auditoría, backups y app instalable. Esta hoja de
ruta arranca desde ahí.

## Adaptación al servicio técnico de PC

| # | Ítem | Estado |
| --- | --- | --- |
| 1 | **Equipos en lugar de vehículos**: tipo (notebook, PC, all-in-one, monitor, impresora, celular, tablet, consola, otro), marca, modelo, n° de serie opcional, procesador, memoria, almacenamiento y color. | ✅ |
| 2 | **Técnicos en lugar de mecánicos** en las órdenes y los reportes. | ✅ |
| 3 | **Ingreso del equipo** en la orden: falla reportada, accesorios recibidos y estado físico; salen en el presupuesto y el comprobante. | ✅ |
| 4 | **Mantenimiento preventivo** por fecha (limpieza, pasta térmica) en lugar del service por kilometraje, con aviso automático. | ✅ |
| 5 | **Portal con DNI + número de orden** en lugar de DNI + patente. | ✅ |
| 6 | **Búsqueda rápida** por n° de serie, marca/modelo, DNI, apellido u orden. | ✅ |
| 7 | **Esquema de base consolidado** en una sola migración inicial, sin el historial ni el script de migración del sistema anterior. | ✅ |
| 8 | **Datos y logo genéricos**: nombre "Servicio Técnico PC" editable desde Configuración y un logo de ejemplo para reemplazar. | ✅ |

## Mejoras traídas del sistema del taller (2026-10-13)

Todo lo que el sistema del taller mecánico sumó después de la copia (sus etapas quinta y sexta,
las revisiones de usabilidad y de código del 2026-10-09, repuestos a costo y del cliente, y la
versión 1.7.0), adaptado al rubro.

| # | Ítem | Estado |
| --- | --- | --- |
| 9 | **"Llegó un equipo"**: DNI → cliente (nuevo o conocido, con la lista de sus equipos) → equipo → orden abierta, en una sola pantalla. Con orden abierta, lo principal es seguir con ella; abrir otra se confirma (también lo exige el servidor, con el equipo bloqueado). | ✅ |
| 10 | **Ficha de la orden guiada** («Qué sigue»), orden sin ítems al recibir, menú por uso, turnos con horarios para tocar, marca y modelo nuevos escribiéndolos, «Imprimir / Enviar» en un menú, panel con tres accesos y cinco números. | ✅ |
| 11 | **Importes a la argentina** ("10.000" son diez mil) en el navegador y el servidor, cantidades sin ambigüedad, teléfonos con código de área real, errores debajo de cada campo y casos compartidos PHP/JS en `tests/casos/` (importes, teléfonos y números de serie). | ✅ |
| 12 | **Usabilidad**: «Deshacer» en lo reversible, buscador con sugerencias, atajos de teclado, borradores que se recuperan, tamaño de letra A− / A+, confirmaciones con verbos, avisos amarillos, accesibilidad. | ✅ |
| 13 | **Repuestos a costo y repuestos que trae el cliente** (del catálogo o escritos a mano), con aviso de garantía configurable. | ✅ |
| 14 | **Tablero del local** con tarjetas que se arrastran; **cómo llegó** el equipo (accesorios, golpes, fotos con la cámara) y **firma del cliente** al recibir y al entregar; **entrega** como paso propio. | ✅ |
| 15 | **WhatsApp con link** (presupuesto, equipo listo, turno, mantenimiento) y **«Para no olvidarse»** en el inicio. Se quitó el canal de WhatsApp Business. | ✅ |
| 16 | **Modo mostrador** (PIN), **primeros pasos** con las marcas comunes de PC de un clic, **tickets térmicos** con QR, **encuesta SUS** y protocolo de pruebas (`docs/usabilidad/`). | ✅ |
| 17 | **Ayuda dentro del sistema** (el manual, con un botón por pantalla), versión automática (`VERSION` + workflow) y tests de controladores. | ✅ |

Lo propio del taller que no aplica quedó afuera: patente, kilometraje, nivel de nafta y año.

## Identidad visual de PC Fighter (2026-10-13)

| # | Ítem | Estado |
| --- | --- | --- |
| 18 | **Paleta y tipografías del sitio de PC Fighter**: azul noche con el fondo de líneas, violeta, magenta, azul y cian; títulos en *Jersey Sharp*, textos en *Saira Semi Condensed* (locales, sin CDN). Contraste mínimo 4.5:1 en los dos temas. | ✅ |
| 19 | **Modo oscuro de la marca por defecto**; el claro, con la misma paleta. Encabezado siempre azul noche, teñido con el color de la sección y con la línea del degradado. | ✅ |
| 20 | **Botones de la marca** (degradado violeta → magenta → azul con resplandor), tarjetas y modales como los del sitio, login, portal del cliente y página sin conexión con la misma estética. | ✅ |

## Ideas para el rubro

| Ítem | Qué falta |
| --- | --- |
| 💭 Contraseña o PIN del equipo en la orden | Definir si se guarda (y cómo, cifrada y visible solo para el técnico) o si se pide en el momento. |
| 💭 Etiqueta imprimible para pegar en el equipo | Número de orden, cliente y fecha de ingreso, con código QR al portal. (El ticket térmico de recepción ya lo cubre en parte.) |
| 💭 Equipos abandonados | Aviso y estado propio para los que no se retiran pasado el plazo. |
| 💭 Facturación electrónica (AFIP/ARCA) | Fuera de alcance por ahora. |
