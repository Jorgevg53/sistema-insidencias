<?php

/*
 * Ticket de la incidencia en PDF. Lo pueden obtener los gestores,
 * quien la reportó y el responsable asignado.
 *
 *   ticket.php?id=5              -> se abre en el navegador
 *   ticket.php?id=5&descargar=1  -> se descarga
 */

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
require_once "../app/helpers/ticket_pdf.php";

requerirSesion();

$id = $_GET["id"] ?? "";

$database = new Database();
$conn = $database->conectar();

$incidencia = ctype_digit((string) $id) ? (new Incidencia($conn))->buscarPorId($id) : false;

$usuario_id = (int) $_SESSION["usuario_id"];

$puedeVer = $incidencia && (
    esGestor() ||
    (int) $incidencia["usuario_id"] === $usuario_id ||
    (int) $incidencia["responsable_id"] === $usuario_id
);

if (!$puedeVer) {
    http_response_code(404);
    exit("Ticket no encontrado.");
}

session_write_close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM evidencias WHERE incidencia_id = ?");
$stmt->execute([$incidencia["id"]]);

$pdf = generarTicketPdf($incidencia, [
    "evidencias" => (int) $stmt->fetchColumn(),
    "impreso_por" => trim($_SESSION["nombre"] . " " . ($_SESSION["apellido_paterno"] ?? ""))
]);

$archivo = "ticket_" . preg_replace('/[^A-Za-z0-9_-]/', "", $incidencia["folio"]) . ".pdf";

header("Content-Type: application/pdf");
header("Content-Length: " . strlen($pdf));
header('Content-Disposition: ' . (isset($_GET["descargar"]) ? "attachment" : "inline") . '; filename="' . $archivo . '"');
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");

echo $pdf;
