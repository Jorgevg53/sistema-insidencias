-- ==========================================================
-- Paso 6: Catálogos administrables
-- ----------------------------------------------------------
-- Ejecutar UNA sola vez sobre la base `sistema_incidencias`
-- existente (después de paso4_notificaciones.sql).
-- Si instalas desde cero con database/database.sql ya viene
-- incluido y no es necesario.
--
-- Nota: "ADD COLUMN IF NOT EXISTS" es propio de MariaDB
-- (el motor que trae XAMPP).
-- ==========================================================

-- Las prioridades ahora se pueden desactivar, igual que las categorías.
ALTER TABLE `prioridades`
  ADD COLUMN IF NOT EXISTS `activo` tinyint(1) NOT NULL DEFAULT 1;
