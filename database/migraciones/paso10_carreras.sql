-- ==========================================================
-- Carreras oficiales
-- ----------------------------------------------------------
-- Opcional: corrige carreras escritas a mano (antes de que
-- existiera la lista desplegable) a su nombre oficial.
-- Se puede ejecutar varias veces sin problema.
-- ==========================================================

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE `usuarios` SET `carrera` = CASE
    WHEN `carrera` IN ('ISC', 'Sistemas', 'Sistemas Computacionales', 'Ing. en Sistemas Computacionales', 'Ing. Sistemas Computacionales', 'Ingenieria en Sistemas Computacionales') THEN 'Ingeniería en Sistemas Computacionales'
    WHEN `carrera` IN ('IND', 'Industrial', 'Ing. Industrial', 'Ingenieria Industrial') THEN 'Ingeniería Industrial'
    WHEN `carrera` IN ('Mecatrónica', 'Mecatronica', 'Ing. Mecatrónica', 'Ingenieria Mecatronica') THEN 'Ingeniería Mecatrónica'
    WHEN `carrera` IN ('Química', 'Quimica', 'Ing. Química', 'Ingenieria Quimica') THEN 'Ingeniería Química'
    WHEN `carrera` IN ('Animación', 'Animacion', 'Ing. en Animación Digital y Efectos Visuales') THEN 'Ingeniería en Animación Digital y Efectos Visuales'
    WHEN `carrera` IN ('Administración', 'Administracion', 'Lic. en Administración') THEN 'Licenciatura en Administración'
    WHEN `carrera` IN ('Gastronomía', 'Gastronomia', 'Lic. en Gastronomía') THEN 'Licenciatura en Gastronomía'
    ELSE `carrera`
END
WHERE `carrera` IS NOT NULL;

UPDATE `incidencias` SET `carrera` = CASE
    WHEN `carrera` IN ('ISC', 'Sistemas', 'Sistemas Computacionales', 'Ing. en Sistemas Computacionales', 'Ing. Sistemas Computacionales', 'Ingenieria en Sistemas Computacionales') THEN 'Ingeniería en Sistemas Computacionales'
    WHEN `carrera` IN ('IND', 'Industrial', 'Ing. Industrial', 'Ingenieria Industrial') THEN 'Ingeniería Industrial'
    WHEN `carrera` IN ('Mecatrónica', 'Mecatronica', 'Ing. Mecatrónica', 'Ingenieria Mecatronica') THEN 'Ingeniería Mecatrónica'
    WHEN `carrera` IN ('Química', 'Quimica', 'Ing. Química', 'Ingenieria Quimica') THEN 'Ingeniería Química'
    WHEN `carrera` IN ('Animación', 'Animacion', 'Ing. en Animación Digital y Efectos Visuales') THEN 'Ingeniería en Animación Digital y Efectos Visuales'
    WHEN `carrera` IN ('Administración', 'Administracion', 'Lic. en Administración') THEN 'Licenciatura en Administración'
    WHEN `carrera` IN ('Gastronomía', 'Gastronomia', 'Lic. en Gastronomía') THEN 'Licenciatura en Gastronomía'
    ELSE `carrera`
END,
`fecha_actualizacion` = `fecha_actualizacion`
WHERE `carrera` IS NOT NULL;

COMMIT;
