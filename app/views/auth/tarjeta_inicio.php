<?php
/*
 * Encabezado de las pantallas sin sesión (recuperar / restablecer).
 * Variables: $tituloPagina
 */
$flash = obtenerFlash();
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Que el enlace con el token no se filtre a otros sitios -->
    <meta name="referrer" content="no-referrer">

    <title><?= e($tituloPagina) ?> | TESCHI</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>TESCHI</h1>

        <h2><?= e($tituloPagina) ?></h2>

        <p>Sistema de Gestión de Incidencias</p>

        <?php if ($flash): ?>
            <div class="alerta alerta-<?= e($flash["tipo"]) ?>"><?= e($flash["mensaje"]) ?></div>
        <?php endif; ?>
