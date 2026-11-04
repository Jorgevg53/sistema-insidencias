<?php

/*
 * Cierre de sesión. Solo por POST y con token CSRF: así otro sitio no
 * puede cerrar la sesión del usuario con un simple enlace o imagen.
 */

require_once "../app/helpers/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . (isset($_SESSION["usuario_id"]) ? "dashboard.php" : "login.php"));
    exit;
}

if (isset($_SESSION["usuario_id"]) && !verificarCsrf()) {
    flash("error", "La sesión del formulario expiró. Intenta cerrar sesión de nuevo.");
    header("Location: dashboard.php");
    exit;
}

cerrarSesion();

header("Location: login.php");

exit;
