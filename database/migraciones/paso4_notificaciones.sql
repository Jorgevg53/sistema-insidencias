-- ==========================================================
-- Paso 4: Notificaciones dentro del sistema
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (después de paso3_seguimiento.sql).
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
-- ==========================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'Destinatario',
  `actor_id` int(11) DEFAULT NULL COMMENT 'Usuario que provocó el aviso',
  `incidencia_id` int(11) DEFAULT NULL,
  `tipo` varchar(30) NOT NULL COMMENT 'nueva, asignacion, estado, comentario, actualizacion',
  `mensaje` varchar(500) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notificacion_usuario_leida` (`usuario_id`, `leida`),
  KEY `fk_notificacion_actor` (`actor_id`),
  KEY `fk_notificacion_incidencia` (`incidencia_id`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notificacion_actor` FOREIGN KEY (`actor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notificacion_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
