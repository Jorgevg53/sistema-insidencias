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

/*
 * Pantallas sin entrada propia en el menú: se resalta la de su módulo.
 */
$paginaActual = [
    "usuario_form.php" => "usuarios.php",
][$paginaActual] ?? $paginaActual;

$menu = [
    "Incidencias" => [
        ["dashboard.php", "Inicio", "inicio", true],
        ["incidencias.php", "Registrar incidencia", "mas", true],
        ["mis_incidencias.php", "Mis incidencias", "lista", true],
        ["asignadas.php", "Asignadas a mí", "bandeja", esGestor()],
        ["todas_incidencias.php", "Todas las incidencias", "capas", esGestor()],
    ],
    "Análisis" => [
        ["reportes.php", "Reportes", "grafica", esGestor()],
    ],
    "Administración" => [
        ["usuarios.php", "Usuarios", "usuarios", tieneRol("Administrador")],
        ["catalogos.php", "Catálogos", "ajustes", tieneRol("Administrador")],
    ],
    "Mi cuenta" => [
        ["notificaciones.php", "Notificaciones", "campana", true],
        ["perfil.php", "Mi perfil", "usuario", true],
    ],
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

$textoContador = $notificacionesNoLeidas > 99 ? "99+" : $notificacionesNoLeidas;

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#04361f">

    <title><?= e($tituloPagina) ?> | TESCHI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    <link rel="stylesheet" href="css/style.css">

</head>

<body class="<?= isset($_SESSION["usuario_id"]) ? "con-menu" : "" ?>">

    <?php if (isset($_SESSION["usuario_id"])): ?>

        <aside class="sidebar" id="menu-lateral" aria-label="Menú principal">

            <div class="sidebar-marca">
                <a href="dashboard.php" class="sidebar-logo" title="Inicio">
                    <img src="img/logo_teschi_web.png" alt="TESCHI">
                </a>
                <div class="sidebar-marca-texto">
                    <strong>Gestión de Incidencias</strong>
                    <span>Ciencias Básicas</span>
                </div>
                <button type="button" class="sidebar-cerrar" data-cerrar-menu aria-label="Cerrar menú">
                    <?= icono("cerrar") ?>
                </button>
            </div>

            <nav class="sidebar-menu">

                <?php foreach ($menu as $seccion => $enlaces): ?>

                    <?php $visibles = array_filter($enlaces, fn($enlace) => $enlace[3]); ?>

                    <?php if ($visibles): ?>

                        <span class="sidebar-seccion"><?= e($seccion) ?></span>

                        <?php foreach ($visibles as [$archivo, $texto, $icono]): ?>

                            <a
                                href="<?= e($archivo) ?>"
                                class="<?= $archivo === $paginaActual ? "activo" : "" ?>"
                                <?= $archivo === $paginaActual ? 'aria-current="page"' : "" ?>
                            >
                                <?= icono($icono) ?>
                                <span><?= e($texto) ?></span>
                                <?php if ($archivo === "notificaciones.php"): ?>
                                    <span
                                        class="sidebar-contador"
                                        data-contador-notificaciones
                                        <?= $notificacionesNoLeidas > 0 ? "" : "hidden" ?>
                                    ><?= $textoContador ?></span>
                                <?php endif; ?>
                            </a>

                        <?php endforeach; ?>

                    <?php endif; ?>

                <?php endforeach; ?>

            </nav>

            <div class="sidebar-usuario">
                <span class="avatar"><?= e(iniciales($_SESSION["nombre"], $_SESSION["apellido_paterno"] ?? "")) ?></span>
                <div class="sidebar-usuario-datos">
                    <strong><?= e($_SESSION["nombre"] . " " . ($_SESSION["apellido_paterno"] ?? "")) ?></strong>
                    <span><?= e($_SESSION["rol"]) ?></span>
                </div>
                <a href="logout.php" class="sidebar-salir" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <?= icono("salir") ?>
                    <span>Cerrar sesión</span>
                </a>
            </div>

        </aside>

        <div class="sidebar-fondo" data-cerrar-menu hidden></div>

    <?php endif; ?>

    <div class="principal">

        <?php if (isset($_SESSION["usuario_id"])): ?>

            <header class="topbar">

                <button
                    type="button"
                    class="topbar-boton topbar-hamburguesa"
                    data-abrir-menu
                    aria-controls="menu-lateral"
                    aria-expanded="false"
                    aria-label="Abrir menú"
                >
                    <?= icono("menu") ?>
                </button>

                <div class="topbar-migas">
                    <span class="topbar-institucion">TESCHI</span>
                    <?= icono("flecha_der", "icono topbar-separador") ?>
                    <span class="topbar-pagina"><?= e($tituloPagina) ?></span>
                </div>

                <div class="topbar-acciones">

                    <a
                        href="notificaciones.php"
                        class="topbar-boton campana"
                        title="Notificaciones"
                        aria-label="Notificaciones"
                    >
                        <?= icono("campana") ?>
                        <span
                            class="campana-contador"
                            data-contador-notificaciones
                            <?= $notificacionesNoLeidas > 0 ? "" : "hidden" ?>
                        ><?= $textoContador ?></span>
                    </a>

                    <a href="perfil.php" class="topbar-perfil" title="Mi perfil">
                        <span class="avatar avatar-sm"><?= e(iniciales($_SESSION["nombre"], $_SESSION["apellido_paterno"] ?? "")) ?></span>
                        <span class="topbar-perfil-datos">
                            <strong><?= e($_SESSION["nombre"]) ?></strong>
                            <span><?= e($_SESSION["rol"]) ?></span>
                        </span>
                    </a>

                </div>

            </header>

        <?php endif; ?>

        <main class="contenedor">

            <div class="impresion-encabezado solo-impresion">
                <img src="img/logo_teschi_web.png" alt="TESCHI">
                <div>
                    <strong>Sistema de Gestión de Incidencias</strong>
                    <span>Tecnológico de Estudios Superiores de Chimalhuacán · Departamento de Ciencias Básicas</span>
                </div>
            </div>

            <?php if ($flash): ?>

                <div class="alerta alerta-<?= e($flash["tipo"]) ?>" role="status">
                    <?= e($flash["mensaje"]) ?>
                </div>

            <?php endif; ?>
