-- ==========================================================
-- Ticket en PDF
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (después de paso8_recuperar_password.sql).
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
--
-- Nota: "ADD COLUMN IF NOT EXISTS" es propio de MariaDB
-- (el motor que trae XAMPP).
-- ==========================================================

SET NAMES utf8mb4;

-- Carrera del usuario (aparece en el ticket).
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `carrera` varchar(150) DEFAULT NULL AFTER `departamento`;

-- Datos de contacto tal como se capturaron al registrar la incidencia
-- (el ticket debe mostrar lo que se entregó ese día).
ALTER TABLE `incidencias`
  ADD COLUMN IF NOT EXISTS `carrera` varchar(150) DEFAULT NULL AFTER `ubicacion`,
  ADD COLUMN IF NOT EXISTS `telefono_contacto` varchar(20) DEFAULT NULL AFTER `carrera`;

-- Tiempo estimado de atención (días hábiles) según la prioridad.
ALTER TABLE `prioridades`
  ADD COLUMN IF NOT EXISTS `dias_atencion` int(11) NOT NULL DEFAULT 3 AFTER `nivel`;

-- Valores iniciales (solo si siguen con el valor por defecto).
UPDATE `prioridades` SET `dias_atencion` = 5 WHERE `nombre` = 'Baja'    AND `dias_atencion` = 3;
UPDATE `prioridades` SET `dias_atencion` = 2 WHERE `nombre` = 'Alta'    AND `dias_atencion` = 3;
UPDATE `prioridades` SET `dias_atencion` = 1 WHERE `nombre` = 'Crítica' AND `dias_atencion` = 3;
