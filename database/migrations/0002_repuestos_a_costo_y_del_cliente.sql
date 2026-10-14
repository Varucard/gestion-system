-- Dos variantes de repuesto en la orden:
--   * a_costo: se cobra al precio de costo, sin margen. Para el cliente es un repuesto más;
--     la marca es interna.
--   * provisto_cliente: lo trae el cliente. Figura en la orden sin precio y no mueve stock.
--     Puede ser un repuesto del catálogo o una pieza escrita a mano (descripcion).
-- Cada ítem es entonces un servicio, un repuesto del catálogo o una pieza descrita a mano
-- (esta última, siempre provista por el cliente).

ALTER TABLE `ordenes_servicios`
  ADD COLUMN `descripcion` varchar(150) DEFAULT NULL AFTER `repuesto_id`,
  ADD COLUMN `a_costo` tinyint(1) NOT NULL DEFAULT 0 AFTER `precio_unitario`,
  ADD COLUMN `provisto_cliente` tinyint(1) NOT NULL DEFAULT 0 AFTER `a_costo`;

ALTER TABLE `ordenes_servicios`
  DROP CHECK `chk_os_item`;

ALTER TABLE `ordenes_servicios`
  ADD CONSTRAINT `chk_os_item` CHECK (
    (`servicio_id` IS NOT NULL) + (`repuesto_id` IS NOT NULL) + (`descripcion` IS NOT NULL) = 1
  ),
  ADD CONSTRAINT `chk_os_descripcion_cliente` CHECK (`descripcion` IS NULL OR `provisto_cliente` = 1),
  ADD CONSTRAINT `chk_os_variante` CHECK (`a_costo` + `provisto_cliente` <= 1 AND (`servicio_id` IS NULL OR `a_costo` + `provisto_cliente` = 0));
