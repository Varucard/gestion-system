-- Modo mostrador: en la PC (o la tablet) que comparten varios, cada uno entra con un PIN de
-- 4 dígitos en vez de usuario y contraseña.
--   * usuarios.pin_hash: el PIN de cada usuario (con password_hash, como la contraseña).
--   * mostradores: los navegadores habilitados como mostrador. El PIN solo sirve desde uno
--     de ellos: el navegador guarda un token en una cookie y acá queda su hash.

ALTER TABLE `usuarios`
  ADD COLUMN `pin_hash` varchar(255) DEFAULT NULL AFTER `password_hash`;

CREATE TABLE IF NOT EXISTS `mostradores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mostradores_token` (`token_hash`),
  CONSTRAINT `fk_mostradores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
