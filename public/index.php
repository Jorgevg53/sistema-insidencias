<?php

/*
 * Punto de entrada: envía al usuario al Dashboard
 * si ya inició sesión, o al login si no.
 */

session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}

exit;
