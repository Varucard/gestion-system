-- =====================================================================
-- Servicio técnico de PC - Esquema inicial
-- Compatible con MySQL 8.x
--
-- Para cambiar la base se agrega un archivo nuevo (0002_descripcion.sql);
-- nunca se modifica uno ya aplicado.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- Personas, clientes y usuarios ----------

CREATE TABLE IF NOT EXISTS `personas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personas_dni` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `persona_id` int NOT NULL,
  `telefono` varchar(10) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `foto` varchar(100) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clientes_persona` (`persona_id`),
  CONSTRAINT `fk_clientes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('administrador','empleado') NOT NULL DEFAULT 'empleado',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Empleados del negocio: comparten la tabla personas con los clientes.
CREATE TABLE IF NOT EXISTS `empleados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `persona_id` int NOT NULL,
  `telefono` varchar(10) DEFAULT NULL,
  `puesto` varchar(50) NOT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empleados_persona` (`persona_id`),
  CONSTRAINT `fk_empleados_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Equipos ----------

CREATE TABLE IF NOT EXISTS `marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marcas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marca_id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modelos_marca_nombre` (`marca_id`, `nombre`),
  CONSTRAINT `fk_modelos_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `tipo` enum('notebook','pc','all_in_one','monitor','impresora','celular','tablet','consola','otro') NOT NULL,
  `marca_id` int NOT NULL,
  `modelo_id` int NOT NULL,
  `numero_serie` varchar(50) DEFAULT NULL,
  `procesador` varchar(80) DEFAULT NULL,
  `memoria` varchar(30) DEFAULT NULL,
  `almacenamiento` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `detalle` text,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipos_numero_serie` (`numero_serie`),
  KEY `idx_equipos_cliente` (`cliente_id`),
  CONSTRAINT `fk_equipos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_equipos_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`),
  CONSTRAINT `fk_equipos_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipo_imagenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipo_id` int NOT NULL,
  `archivo` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eimg_equipo` (`equipo_id`),
  CONSTRAINT `fk_eimg_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `fk_eimg_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Catálogo y stock ----------

CREATE TABLE IF NOT EXISTS `servicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `precio_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servicios_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `cuit` varchar(13) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `observaciones` text,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proveedores_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repuestos y componentes (discos, memorias, fuentes, pantallas, cargadores…).
CREATE TABLE IF NOT EXISTS `repuestos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `stock_actual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_minimo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `proveedor_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repuestos_codigo` (`codigo`),
  CONSTRAINT `fk_repuestos_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_combos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `combo_id` int NOT NULL,
  `servicio_id` int DEFAULT NULL,
  `repuesto_id` int DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
  PRIMARY KEY (`id`),
  KEY `idx_combo_items_combo` (`combo_id`),
  CONSTRAINT `fk_combo_items_combo` FOREIGN KEY (`combo_id`) REFERENCES `combos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_combo_items_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`),
  CONSTRAINT `fk_combo_items_repuesto` FOREIGN KEY (`repuesto_id`) REFERENCES `repuestos` (`id`),
  CONSTRAINT `chk_combo_items_item` CHECK ((`servicio_id` IS NULL) <> (`repuesto_id` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Turnos ----------

CREATE TABLE IF NOT EXISTS `turnos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `equipo_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `descripcion` text,
  `estado` enum('pendiente','confirmado','realizado','cancelado','no_asistio') NOT NULL DEFAULT 'pendiente',
  `recordatorio_enviado` datetime DEFAULT NULL,
  `recordatorio_canal` varchar(20) DEFAULT NULL,
  `token` char(64) DEFAULT NULL COMMENT 'Link público para que el cliente confirme o cancele',
  `confirmacion_enviada` datetime DEFAULT NULL,
  `respuesta_cliente` enum('confirmado','cancelado') DEFAULT NULL,
  `respuesta_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_turnos_token` (`token`),
  KEY `idx_turnos_fecha_hora` (`fecha`, `hora`),
  KEY `idx_turnos_cliente` (`cliente_id`),
  KEY `idx_turnos_equipo` (`equipo_id`),
  CONSTRAINT `fk_turnos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_turnos_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Órdenes de reparación ----------

-- Cada orden guarda a qué cliente pertenecía al hacerse: si el equipo cambia de
-- dueño, el historial, los saldos y el portal del cliente anterior siguen siendo suyos.
CREATE TABLE IF NOT EXISTS `ordenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipo_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `tecnico_id` int DEFAULT NULL,
  `turno_id` int DEFAULT NULL,
  `falla_reportada` text,
  `accesorios` varchar(255) DEFAULT NULL COMMENT 'Lo que el cliente dejó con el equipo (cargador, funda…)',
  `estado_ingreso` text COMMENT 'Estado físico al recibirlo (golpes, rayones, teclas faltantes…)',
  `diagnostico` text,
  `trabajo_realizado` text,
  `notas_internas` text,
  `proximo_mantenimiento_fecha` date DEFAULT NULL,
  `proximo_mantenimiento_avisado` datetime DEFAULT NULL,
  `fecha_realizado` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizado','cancelado') NOT NULL DEFAULT 'pendiente',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_descontado` tinyint(1) NOT NULL DEFAULT 0,
  `token` char(64) DEFAULT NULL COMMENT 'Link público del presupuesto',
  `presupuesto_enviado` datetime DEFAULT NULL,
  `presupuesto_respuesta` enum('aceptado','rechazado') DEFAULT NULL,
  `presupuesto_respuesta_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ordenes_token` (`token`),
  KEY `idx_ordenes_equipo` (`equipo_id`),
  KEY `idx_ordenes_cliente` (`cliente_id`),
  KEY `idx_ordenes_tecnico` (`tecnico_id`),
  KEY `idx_ordenes_proximo_mantenimiento` (`proximo_mantenimiento_fecha`),
  KEY `idx_ordenes_created` (`created_at`),
  CONSTRAINT `fk_ordenes_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `fk_ordenes_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_ordenes_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `empleados` (`id`),
  CONSTRAINT `fk_ordenes_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ítems de la orden: cada fila es un servicio O un repuesto (nunca ambos).
-- El precio se congela al agregar el ítem; `costo` es el subtotal (cantidad × precio).
CREATE TABLE IF NOT EXISTS `ordenes_servicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `servicio_id` int DEFAULT NULL,
  `repuesto_id` int DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `costo` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_os_orden` (`orden_id`),
  KEY `idx_os_servicio` (`servicio_id`),
  KEY `idx_os_repuesto` (`repuesto_id`),
  CONSTRAINT `fk_os_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_os_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`),
  CONSTRAINT `fk_os_repuesto` FOREIGN KEY (`repuesto_id`) REFERENCES `repuestos` (`id`),
  CONSTRAINT `chk_os_item` CHECK ((`servicio_id` IS NULL) <> (`repuesto_id` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `movimientos_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `repuesto_id` int NOT NULL,
  `tipo` enum('ingreso','egreso','ajuste') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Positiva suma stock, negativa resta',
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `stock_resultante` decimal(10,2) NOT NULL,
  `orden_id` int DEFAULT NULL,
  `proveedor_id` int DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_repuesto_fecha` (`repuesto_id`, `created_at`),
  CONSTRAINT `fk_mov_repuesto` FOREIGN KEY (`repuesto_id`) REFERENCES `repuestos` (`id`),
  CONSTRAINT `fk_mov_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagos (totales o parciales) de las órdenes.
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `forma_pago` varchar(50) NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pagos_orden` (`orden_id`),
  KEY `idx_pagos_fecha` (`fecha`),
  CONSTRAINT `fk_pagos_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`),
  CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_pagos_monto` CHECK (`monto` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Avisos, accesos y auditoría ----------

-- Avisos enviados a clientes (turnos, presupuestos, equipo listo, mantenimiento).
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turno_id` int DEFAULT NULL,
  `orden_id` int DEFAULT NULL,
  `tipo` varchar(30) NOT NULL,
  `canal` varchar(20) NOT NULL,
  `destino` varchar(255) NOT NULL,
  `estado` enum('enviado','error') NOT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_turno` (`turno_id`),
  KEY `idx_notif_orden` (`orden_id`),
  KEY `idx_notif_fecha` (`created_at`),
  CONSTRAINT `fk_notif_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control genérico de intentos fallidos (login y portal de seguimiento).
CREATE TABLE IF NOT EXISTS `intentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ambito` varchar(20) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intentos_clave` (`ambito`, `clave`, `created_at`),
  KEY `idx_intentos_ip` (`ambito`, `ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoría: quién hizo qué y cuándo sobre los datos del negocio.
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL COMMENT 'Copia del nombre al momento del cambio',
  `accion` varchar(50) NOT NULL,
  `entidad` varchar(30) NOT NULL,
  `entidad_id` int DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `datos` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_entidad` (`entidad`, `entidad_id`),
  KEY `idx_auditoria_fecha` (`created_at`),
  KEY `idx_auditoria_usuario` (`usuario_id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
