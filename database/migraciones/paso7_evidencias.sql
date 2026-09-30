-- ==========================================================
-- Extra: Evidencias (archivos adjuntos)
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (después de paso6_catalogos.sql).
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
-- ==========================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `comentario_id` int(11) DEFAULT NULL COMMENT 'NULL = adjuntada al registrar la incidencia',
  `usuario_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `archivo` varchar(100) NOT NULL COMMENT 'Nombre aleatorio en storage/evidencias',
  `tipo_mime` varchar(100) NOT NULL,
  `tamano` int(11) NOT NULL COMMENT 'Bytes',
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `archivo` (`archivo`),
  KEY `fk_evidencia_incidencia` (`incidencia_id`),
  KEY `fk_evidencia_comentario` (`comentario_id`),
  KEY `fk_evidencia_usuario` (`usuario_id`),
  CONSTRAINT `fk_evidencia_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidencia_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `comentarios_incidencia` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
