-- Cuándo se llevó el cliente el equipo, y las fotos y la firma de la recepción.
--   * entregado_en: el cliente retiró el equipo. Una orden "Lista" sin entregar es un equipo
--     que todavía está en el local (tablero, alertas de equipos sin retirar).
--   * Las fotos de la recepción son imágenes del equipo atadas a la orden en la que se
--     sacaron (orden_id), así se ven en la orden y también en la galería del equipo.
--   * orden_firmas: la conformidad del cliente firmada en la pantalla, al recibir el equipo
--     (con los accesorios y el estado en que llega) y al entregarlo (con los trabajos hechos).

ALTER TABLE `ordenes`
  ADD COLUMN `entregado_en` datetime DEFAULT NULL AFTER `fecha_realizado`;

ALTER TABLE `equipo_imagenes`
  ADD COLUMN `orden_id` int DEFAULT NULL AFTER `equipo_id`,
  ADD KEY `idx_eimg_orden` (`orden_id`),
  ADD CONSTRAINT `fk_eimg_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `orden_firmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `momento` enum('recepcion','entrega') NOT NULL,
  `archivo` varchar(100) NOT NULL,
  `aclaracion` varchar(100) DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_orden_momento` (`orden_id`, `momento`),
  CONSTRAINT `fk_firma_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`),
  CONSTRAINT `fk_firma_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Antes no se registraba la entrega: las órdenes que se terminaron hace más de una semana se
-- dan por entregadas el día en que se terminaron (si no, el tablero y las alertas las
-- mostrarían todas como equipos sin retirar). Las de la última semana quedan como listas: si
-- ya se retiraron, se marca con "Entregar el equipo"; las que sigan en el local, se ven.
UPDATE `ordenes`
   SET `entregado_en` = TIMESTAMP(COALESCE(`fecha_realizado`, DATE(`created_at`)))
 WHERE `estado` = 'finalizado' AND `entregado_en` IS NULL
   AND COALESCE(`fecha_realizado`, DATE(`created_at`)) < CURDATE() - INTERVAL 7 DAY;
