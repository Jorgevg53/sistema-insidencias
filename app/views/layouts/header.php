<?php
/*
|--------------------------------------------------------------------------
| ENCABEZADO COMÚN
|--------------------------------------------------------------------------
| Variables opcionales antes de incluirlo:
|   $tituloPagina  -> texto de la pestaña del navegador
|   $paginaActual  -> nombre del archivo para resaltar el menú
*/

$tituloPagina = $tituloPagina ?? "Sistema de Incidencias";
$paginaActual = $paginaActual ?? basename($_SERVER["PHP_SELF"]);

$menu = [
    ["dashboard.php", "Dashboard", true],
    ["incidencias.php", "Registrar incidencia", true],
    ["mis_incidencias.php", "Mis incidencias", true],
    ["todas_incidencias.php", "Todas las incidencias", esGestor()],
    ["usuarios.php", "Usuarios", tieneRol("Administrador")],
    ["perfil.php", "Mi perfil", true],
];

$flash = obtenerFlash();

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($tituloPagina) ?> | TESCHI</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body>

    <header class="topbar">

        <div class="topbar-marca">
            <strong>TESCHI</strong>
            <span>Sistema de Gestión de Incidencias · Ciencias Básicas</span>
        </div>

        <?php if (isset($_SESSION["usuario_id"])): ?>

            <div class="topbar-usuario">
                <?= e($_SESSION["nombre"] . " " . ($_SESSION["apellido_paterno"] ?? "")) ?>
                <span class="badge"><?= e($_SESSION["rol"]) ?></span>
                <a href="logout.php">Cerrar sesión</a>
            </div>

        <?php endif; ?>

    </header>

    <?php if (isset($_SESSION["usuario_id"])): ?>

        <nav class="menu">

            <?php foreach ($menu as [$archivo, $texto, $visible]): ?>

                <?php if ($visible): ?>

                    <a
                        href="<?= e($archivo) ?>"
                        class="<?= $archivo === $paginaActual ? "activo" : "" ?>"
                    >
                        <?= e($texto) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

        </nav>

    <?php endif; ?>

    <main class="contenedor">

        <?php if ($flash): ?>

            <div class="alerta alerta-<?= e($flash["tipo"]) ?>">
                <?= e($flash["mensaje"]) ?>
            </div>

        <?php endif; ?>
