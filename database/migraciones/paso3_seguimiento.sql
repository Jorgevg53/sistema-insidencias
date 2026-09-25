-- ==========================================================
-- Paso 3: Seguimiento de incidencias
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (phpMyAdmin > SQL > pegar y "Continuar").
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
-- ==========================================================

START TRANSACTION;

-- ----------------------------------------------------------
-- Historial: cada registro, cambio de estado o asignación
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `historial_incidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(30) NOT NULL COMMENT 'registro, estado, asignacion',
  `estado_anterior_id` int(11) DEFAULT NULL,
  `estado_nuevo_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_historial_incidencia` (`incidencia_id`),
  KEY `fk_historial_usuario` (`usuario_id`),
  KEY `fk_historial_estado_anterior` (`estado_anterior_id`),
  KEY `fk_historial_estado_nuevo` (`estado_nuevo_id`),
  CONSTRAINT `fk_historial_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_historial_estado_anterior` FOREIGN KEY (`estado_anterior_id`) REFERENCES `estados_incidencia` (`id`),
  CONSTRAINT `fk_historial_estado_nuevo` FOREIGN KEY (`estado_nuevo_id`) REFERENCES `estados_incidencia` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Comentarios de seguimiento
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `comentarios_incidencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_comentario_incidencia` (`incidencia_id`),
  KEY `fk_comentario_usuario` (`usuario_id`),
  CONSTRAINT `fk_comentario_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comentario_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Datos existentes
-- ----------------------------------------------------------

-- Evento de "registro" para las incidencias que ya existían.
INSERT INTO `historial_incidencias`
  (`incidencia_id`, `usuario_id`, `accion`, `estado_nuevo_id`, `descripcion`, `fecha`)
SELECT i.id, i.usuario_id, 'registro', 1, 'Incidencia registrada', i.fecha_registro
FROM `incidencias` i
WHERE NOT EXISTS (
  SELECT 1 FROM `historial_incidencias` h
  WHERE h.incidencia_id = i.id AND h.accion = 'registro'
);

-- Incidencias ya terminadas sin fecha de cierre (cerradas con el código anterior).
-- Se conserva fecha_actualizacion para que no cambie por el ON UPDATE.
UPDATE `incidencias`
SET `fecha_cierre` = `fecha_actualizacion`,
    `fecha_actualizacion` = `fecha_actualizacion`
WHERE `estado_id` IN (5, 6, 7)
  AND `fecha_cierre` IS NULL;

COMMIT;
