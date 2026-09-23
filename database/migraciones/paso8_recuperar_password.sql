-- ==========================================================
-- Recuperación de contraseña
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (después de paso7_evidencias.sql).
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
-- ==========================================================

START TRANSACTION;

-- Cada solicitud y cada enlace de restablecimiento generado.
-- El token NUNCA se guarda en texto plano: solo su hash SHA-256.
CREATE TABLE IF NOT EXISTS `restablecimientos_password` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'NULL si el correo solicitado no existe',
  `correo` varchar(150) NOT NULL,
  `origen` varchar(20) NOT NULL COMMENT 'solicitud, correo, administrador',
  `token_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 del token; NULL en solicitudes',
  `creado_por` int(11) DEFAULT NULL COMMENT 'Administrador que generó el enlace',
  `expira` datetime DEFAULT NULL,
  `usado_en` datetime DEFAULT NULL COMMENT 'Uso o anulación del enlace',
  `ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_restablecimiento_correo_fecha` (`correo`, `fecha`),
  KEY `idx_restablecimiento_ip_fecha` (`ip`, `fecha`),
  KEY `fk_restablecimiento_usuario` (`usuario_id`),
  KEY `fk_restablecimiento_creado_por` (`creado_por`),
  CONSTRAINT `fk_restablecimiento_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restablecimiento_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
