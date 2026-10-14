-- Encuesta de usabilidad SUS (System Usability Scale): 10 preguntas de 1 a 5 que dan un
-- puntaje de 0 a 100. Se toma por rondas ("Antes de los cambios", "Después…") para comparar.
-- Cada usuario responde una vez por ronda.

CREATE TABLE IF NOT EXISTS `encuestas_sus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `ronda` varchar(60) NOT NULL,
  `respuestas` varchar(40) NOT NULL,
  `puntaje` decimal(5,2) NOT NULL,
  `comentario` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sus_usuario_ronda` (`usuario_id`, `ronda`),
  KEY `idx_sus_ronda` (`ronda`),
  CONSTRAINT `fk_sus_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
