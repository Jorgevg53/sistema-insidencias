<?php

/*
 * Devuelve en JSON cuántas notificaciones sin leer tiene el usuario.
 * Lo consulta js/app.js cada minuto para actualizar la campana del menú.
 */

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Notificacion.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Sesión no iniciada"]);
    exit;
}

/*
 * Se libera el bloqueo de la sesión: esta petición solo lee.
 */
$usuario_id = (int) $_SESSION["usuario_id"];

session_write_close();

$database = new Database();

$notificacionModel = new Notificacion($database->conectar());

echo json_encode([
    "no_leidas" => $notificacionModel->contarNoLeidas($usuario_id)
]);
