<?php

/*
 * Descarga el listado de incidencias (con los filtros del reporte)
 * en formato CSV, que se abre directamente en Excel.
 */

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Reporte.php";

requerirRol(["Administrador", "Coordinador"]);

$database = new Database();
$conn = $database->conectar();

$categorias = $conn->query("SELECT id, nombre FROM categorias")->fetchAll(PDO::FETCH_KEY_PAIR);
$prioridades = $conn->query("SELECT id, nombre FROM prioridades")->fetchAll(PDO::FETCH_KEY_PAIR);

$filtros = Reporte::filtrosDesdeRequest($_GET, $categorias, $prioridades);

$reporte = new Reporte($conn, $filtros);

$filas = $reporte->listadoDetallado();

/*
 * Evita que Excel ejecute como fórmula un texto que empiece con
 * =, +, - o @ (inyección de fórmulas en CSV).
 */
function celdaSegura($valor)
{
    $valor = (string) ($valor ?? "");

    if ($valor !== "" && in_array($valor[0], ["=", "+", "-", "@", "\t", "\r"], true)) {
        return "'" . $valor;
    }

    return $valor;
}

$archivo = "reporte_incidencias_" . date("Ymd_His") . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header("Cache-Control: no-store");

$salida = fopen("php://output", "w");

// BOM para que Excel reconozca los acentos (UTF-8).
fwrite($salida, "\xEF\xBB\xBF");

fputcsv($salida, [
    "Folio",
    "Título",
    "Categoría",
    "Prioridad",
    "Estado",
    "Reportó",
    "Correo",
    "Carrera",
    "Teléfono de contacto",
    "Responsable",
    "Ubicación",
    "Fecha de registro",
    "Fecha de cierre",
    "Horas de resolución",
    "Descripción"
], ",", '"', "");

foreach ($filas as $fila) {
    fputcsv($salida, array_map("celdaSegura", array_values($fila)), ",", '"', "");
}

fclose($salida);
