<?php
/*
 * Encabezado de las pantallas sin sesión (login / recuperar / restablecer).
 * Variables: $tituloPagina, $subtituloAcceso (opcional)
 */
$flash = obtenerFlash();
$subtituloAcceso = $subtituloAcceso ?? "Incidencias del Sistema de Calificaciones";
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#04361f">

    <!-- Que el enlace con el token no se filtre a otros sitios -->
    <meta name="referrer" content="no-referrer">

    <title><?= e($tituloPagina) ?> | TESCHI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    <link rel="stylesheet" href="css/style.css">

</head>

<body>

<div class="acceso">

    <section class="acceso-portada" aria-hidden="true">

        <div class="acceso-portada-contenido">

            <span class="acceso-sello">
                <?= icono("escudo") ?>
                Departamento de Ciencias Básicas
            </span>

            <h2>Reporta y da seguimiento a las incidencias del sistema de calificaciones.</h2>

            <ul class="acceso-beneficios">
                <li><?= icono("rayo") ?> Registro rápido con evidencias y folio único</li>
                <li><?= icono("historial") ?> Seguimiento en tiempo real de cada reporte</li>
                <li><?= icono("grafica") ?> Reportes y tiempos de atención por área</li>
            </ul>

        </div>

        <p class="acceso-portada-pie">Tecnológico de Estudios Superiores de Chimalhuacán</p>

    </section>

    <section class="acceso-panel">

        <div class="acceso-tarjeta">

            <h1 class="acceso-logo">
                <img src="img/logo_teschi_web.png" alt="TESCHI · Tecnológico de Estudios Superiores de Chimalhuacán">
            </h1>

            <div class="acceso-titulo">
                <h2><?= e($tituloPagina) ?></h2>
                <p><?= e($subtituloAcceso) ?></p>
            </div>

            <?php if ($flash): ?>
                <div class="alerta alerta-<?= e($flash["tipo"]) ?>" role="status"><?= e($flash["mensaje"]) ?></div>
            <?php endif; ?>
