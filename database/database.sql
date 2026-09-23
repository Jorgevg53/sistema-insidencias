-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-09-2026 a las 05:24:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_incidencias`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Académica', 'Incidencias relacionadas con actividades académicas', 1),
(2, 'Administrativa', 'Incidencias relacionadas con procesos administrativos', 1),
(3, 'Infraestructura', 'Problemas relacionados con instalaciones', 1),
(4, 'Equipo de cómputo', 'Problemas con computadoras o dispositivos', 1),
(5, 'Software', 'Problemas relacionados con sistemas o aplicaciones', 1),
(6, 'Redes', 'Problemas relacionados con conectividad y redes', 1),
(7, 'Control Escolar', 'Incidencias relacionadas con Control Escolar', 1),
(8, 'Otra', 'Otras incidencias', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_incidencia`
--

CREATE TABLE `estados_incidencia` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `estados_incidencia`
--

INSERT INTO `estados_incidencia` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Pendiente', 'La incidencia fue registrada y está pendiente de revisión'),
(2, 'En revisión', 'La incidencia está siendo revisada'),
(3, 'Asignada', 'La incidencia fue asignada a un responsable'),
(4, 'En proceso', 'La incidencia está siendo atendida'),
(5, 'Resuelta', 'La incidencia fue solucionada'),
(6, 'Cerrada', 'La incidencia fue finalizada'),
(7, 'Cancelada', 'La incidencia fue cancelada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL,
  `folio` varchar(30) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `prioridad_id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `ubicacion` varchar(200) DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `incidencias`
--

INSERT INTO `incidencias` (`id`, `folio`, `usuario_id`, `categoria_id`, `prioridad_id`, `estado_id`, `titulo`, `descripcion`, `ubicacion`, `responsable_id`, `fecha_registro`, `fecha_actualizacion`, `fecha_cierre`) VALUES
(1, 'INC-20260922-184829', 1, 4, 2, 4, 'LABORATORIO COMPUTO', 'EL EQUIPO NO FUNCIONA BIEN', 'EDO MEX', NULL, '2026-09-22 16:48:29', '2026-09-23 02:08:28', NULL),
(2, 'INC-20260922-184859', 1, 4, 2, 1, 'LABORATORIO COMPUTO', 'EL EQUIPO NO FUNCIONA BIEN', 'EDO MEX', NULL, '2026-09-22 16:48:59', '2026-09-22 16:48:59', NULL),
(3, 'INC-20260922-185008', 1, 8, 2, 6, 'ECONOMICA', 'SE ME CAYO EL TECLADO EN MI PIE JAJAJA', 'LABORATORIO BASES DE DATOS', NULL, '2026-09-22 16:50:08', '2026-09-23 02:06:52', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prioridades`
--

CREATE TABLE `prioridades` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `prioridades`
--

INSERT INTO `prioridades` (`id`, `nombre`, `nivel`) VALUES
(1, 'Baja', 1),
(2, 'Media', 2),
(3, 'Alta', 3),
(4, 'Crítica', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Administrador', 'Acceso completo al sistema', 1),
(2, 'Coordinador', 'Gestión de incidencias del Departamento de Ciencias Básicas', 1),
(3, 'Docente', 'Registro y seguimiento de incidencias docentes', 1),
(4, 'Administrativo', 'Registro y seguimiento de incidencias administrativas', 1),
(5, 'Estudiante', 'Registro y consulta de incidencias', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `matricula` varchar(30) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) DEFAULT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `departamento` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `matricula`, `nombre`, `apellido_paterno`, `apellido_materno`, `correo`, `password`, `rol_id`, `departamento`, `telefono`, `activo`, `fecha_registro`) VALUES
(1, NULL, 'Administrador', 'TESCHI', '', 'admin@teschi.edu.mx', '$2y$10$X8AdscH/ONSn04QK1Ia5De2zrEhP8PX9QvxaTXOhD77UBhRtzx5rW', 1, NULL, NULL, 1, '2026-09-22 16:24:14');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `estados_incidencia`
--
ALTER TABLE `estados_incidencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio` (`folio`),
  ADD KEY `fk_incidencia_usuario` (`usuario_id`),
  ADD KEY `fk_incidencia_categoria` (`categoria_id`),
  ADD KEY `fk_incidencia_prioridad` (`prioridad_id`),
  ADD KEY `fk_incidencia_estado` (`estado_id`),
  ADD KEY `fk_incidencia_responsable` (`responsable_id`);

--
-- Indices de la tabla `prioridades`
--
ALTER TABLE `prioridades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `fk_usuario_rol` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `estados_incidencia`
--
ALTER TABLE `estados_incidencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `prioridades`
--
ALTER TABLE `prioridades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_incidencia_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  ADD CONSTRAINT `fk_incidencia_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados_incidencia` (`id`),
  ADD CONSTRAINT `fk_incidencia_prioridad` FOREIGN KEY (`prioridad_id`) REFERENCES `prioridades` (`id`),
  ADD CONSTRAINT `fk_incidencia_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_incidencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);

-- --------------------------------------------------------
-- Paso 3: Seguimiento (historial y comentarios)
-- --------------------------------------------------------

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


-- --------------------------------------------------------
-- Paso 4: Notificaciones
-- --------------------------------------------------------

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


-- --------------------------------------------------------
-- Extra: Evidencias (archivos adjuntos)
-- --------------------------------------------------------

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
