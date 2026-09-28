<?php

/*
 * Entrega un archivo de evidencia solo a quien puede ver la incidencia:
 * gestores, quien la reportó y el responsable asignado.
 *
 *   evidencia.php?id=5              -> se muestra en el navegador
 *   evidencia.php?id=5&descargar=1  -> se descarga
 */

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
require_once "../app/models/Evidencia.php";

requerirSesion();

$id = $_GET["id"] ?? "";

$database = new Database();
$conn = $database->conectar();

$evidencia = ctype_digit((string) $id) ? (new Evidencia($conn))->buscarPorId($id) : false;
$incidencia = $evidencia ? (new Incidencia($conn))->buscarPorId($evidencia["incidencia_id"]) : false;

$usuario_id = (int) $_SESSION["usuario_id"];

$puedeVer = $incidencia && (
    esGestor() ||
    (int) $incidencia["usuario_id"] === $usuario_id ||
    (int) $incidencia["responsable_id"] === $usuario_id
);

$ruta = $evidencia ? Evidencia::ruta($evidencia["archivo"]) : "";

if (!$puedeVer || !is_file($ruta)) {
    http_response_code(404);
    exit("Archivo no encontrado.");
}

session_write_close();

$disposicion = isset($_GET["descargar"]) ? "attachment" : "inline";

// Nombre seguro para el encabezado (el original va codificado aparte).
$nombreAscii = preg_replace('/[^A-Za-z0-9._-]/', "_", $evidencia["nombre_original"]);

header("Content-Type: " . $evidencia["tipo_mime"]);
header("Content-Length: " . filesize($ruta));
header(
    "Content-Disposition: " . $disposicion
    . '; filename="' . $nombreAscii . '"'
    . "; filename*=UTF-8''" . rawurlencode($evidencia["nombre_original"])
);
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=3600");

readfile($ruta);
