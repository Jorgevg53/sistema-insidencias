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
    ["asignadas.php", "Asignadas a mí", tieneRol("Administrador", "Coordinador", "Docente", "Administrativo")],
    ["todas_incidencias.php", "Todas las incidencias", esGestor()],
    ["reportes.php", "Reportes", esGestor()],
    ["usuarios.php", "Usuarios", tieneRol("Administrador")],
    ["catalogos.php", "Catálogos", tieneRol("Administrador")],
    ["perfil.php", "Mi perfil", true],
];

$flash = obtenerFlash();

/*
 * Contador de notificaciones sin leer para la campana.
 */
$notificacionesNoLeidas = 0;

if (isset($_SESSION["usuario_id"])) {

    require_once __DIR__ . "/../../config/database.php";
    require_once __DIR__ . "/../../models/Notificacion.php";

    $notificacionesNoLeidas = (new Notificacion((new Database())->conectar()))
        ->contarNoLeidas($_SESSION["usuario_id"]);
}

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
            <a href="dashboard.php" class="topbar-logo" title="Inicio">
                <img src="img/logo_teschi_web.png" alt="TESCHI">
            </a>
            <div>
                <strong>Sistema de Gestión de Incidencias</strong>
                <span>Departamento de Ciencias Básicas</span>
            </div>
        </div>

        <?php if (isset($_SESSION["usuario_id"])): ?>

            <div class="topbar-usuario">

                <a
                    href="notificaciones.php"
                    class="campana"
                    title="Notificaciones"
                    aria-label="Notificaciones"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span
                        class="campana-contador"
                        data-contador-notificaciones
                        <?= $notificacionesNoLeidas > 0 ? "" : "hidden" ?>
                    ><?= $notificacionesNoLeidas > 99 ? "99+" : $notificacionesNoLeidas ?></span>
                </a>

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
