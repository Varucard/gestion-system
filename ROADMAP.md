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

## Ideas para el rubro

| Ítem | Qué falta |
| --- | --- |
| 💭 Contraseña o PIN del equipo en la orden | Definir si se guarda (y cómo, cifrada y visible solo para el técnico) o si se pide en el momento. |
| 💭 Etiqueta imprimible para pegar en el equipo | Número de orden, cliente y fecha de ingreso, con código QR al portal. |
| 💭 Firma del cliente al recibir y al retirar | Conformidad sobre accesorios y estado físico. |
| 💭 Equipos abandonados | Aviso y estado propio para los que no se retiran pasado el plazo. |
| 💭 Activar WhatsApp Business (API de Meta) | Cuenta verificada, número dedicado, plantillas aprobadas e implementar `WhatsAppCanal::enviar()`. |
| 💭 Facturación electrónica (AFIP/ARCA) | Fuera de alcance por ahora. |
