<?php

/*
|--------------------------------------------------------------------------
| CORREOS DE INCIDENCIAS
|--------------------------------------------------------------------------
| - Aviso por correo de cada notificación del sistema.
| - Ticket en PDF adjunto al registrar una incidencia.
|
| Son "de mejor esfuerzo": si el correo no está configurado o falla el envío,
| el sistema sigue funcionando igual y el motivo queda en el log de Apache.
| Se activan con "habilitado" y "notificar_por_correo" en app/config/correo.php
| (o correo.local.php).
*/

require_once __DIR__ . "/correo.php";

function notificarPorCorreoActivo()
{
    $config = configCorreo();

    return correoHabilitado() && ($config["notificar_por_correo"] ?? true);
}

/*
 * Plantilla HTML sencilla con la identidad del sistema.
 */
function plantillaCorreo($titulo, $parrafos, $enlace = null, $textoEnlace = "Abrir la incidencia")
{
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #d5e2d8;border-radius:10px;overflow:hidden">'
        . '<div style="background:#007a3d;color:#fff;padding:14px 18px;font-weight:bold">Sistema de Gestión de Incidencias · TESCHI</div>'
        . '<div style="padding:18px;color:#1f2a24;line-height:1.5">'
        . '<h2 style="margin:0 0 12px;color:#005a2d;font-size:18px">' . e($titulo) . '</h2>';

    foreach ($parrafos as $p) {
        $html .= '<p style="margin:0 0 10px">' . e($p) . '</p>';
    }

    if ($enlace) {
        $html .= '<p style="margin:16px 0 0"><a href="' . e($enlace) . '" style="background:#007a3d;color:#fff;'
            . 'padding:10px 18px;border-radius:20px;text-decoration:none;font-weight:bold">' . e($textoEnlace) . '</a></p>';
    }

    return $html . '</div><div style="background:#f6faf6;color:#5f6b64;font-size:12px;padding:10px 18px">'
        . 'Departamento de Ciencias Básicas · Tecnológico de Estudios Superiores de Chimalhuacán. '
        . 'Este es un mensaje automático; no respondas a este correo.</div></div>';
}

/*
 * Envía por correo el aviso de una notificación. $db es MongoDB\Database.
 */
function correoDeNotificacion($db, $usuario_id, $incidencia_id, $mensaje)
{
    if (!notificarPorCorreoActivo()) {
        return;
    }

    try {

        $usuario = $db->getCollection("usuarios")->findOne(
            ["_id" => (int) $usuario_id, "activo" => true],
            ["projection" => ["nombre" => 1, "correo" => 1]]
        );

        $incidencia = $db->getCollection("incidencias")->findOne(
            ["_id" => (int) $incidencia_id],
            ["projection" => ["folio" => 1, "titulo" => 1]]
        );

        if (!$usuario || !$incidencia) {
            return;
        }

        $enlace = rtrim(configCorreo()["url_base"], "/") . "/detalle_incidencia.php?id=" . (int) $incidencia_id;

        enviarCorreo(
            $usuario["correo"],
            "[" . $incidencia["folio"] . "] " . mb_substr($mensaje, 0, 90),
            plantillaCorreo(
                "Novedad en la incidencia " . $incidencia["folio"],
                ["Hola " . $usuario["nombre"] . ",", $mensaje . ".", "Incidencia: " . $incidencia["titulo"]],
                $enlace
            ),
            "Hola " . $usuario["nombre"] . ",\n\n" . $mensaje . ".\nIncidencia: " . $incidencia["folio"] . " · "
                . $incidencia["titulo"] . "\n\nÁbrela aquí: " . $enlace
        );

    } catch (Throwable $e) {

        error_log("Correo de notificación no enviado: " . $e->getMessage());
    }
}

/*
 * Envía al solicitante su ticket en PDF (adjunto) al registrar la incidencia.
 */
function correoDeTicket($db, $incidencia_id)
{
    if (!notificarPorCorreoActivo()) {
        return;
    }

    try {

        require_once __DIR__ . "/../models/Incidencia.php";
        require_once __DIR__ . "/ticket_pdf.php";

        $incidencia = (new Incidencia($db))->buscarPorId($incidencia_id);

        if (!$incidencia || !filter_var($incidencia["correo"], FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $doc = $db->getCollection("incidencias")->findOne(
            ["_id" => (int) $incidencia_id],
            ["projection" => ["evidencias" => 1]]
        );

        $pdf = generarTicketPdf($incidencia, [
            "evidencias" => isset($doc["evidencias"]) ? count($doc["evidencias"]) : 0,
            "impreso_por" => trim($incidencia["nombre"] . " " . $incidencia["apellido_paterno"]),
        ]);

        $enlace = rtrim(configCorreo()["url_base"], "/") . "/detalle_incidencia.php?id=" . (int) $incidencia_id;
        $vence = Incidencia::fechaCompromiso($incidencia["fecha_registro"], $incidencia["dias_atencion"])->format("d/m/Y");

        enviarCorreo(
            $incidencia["correo"],
            "Tu ticket " . $incidencia["folio"],
            plantillaCorreo(
                "Registramos tu incidencia",
                [
                    "Hola " . $incidencia["nombre"] . ",",
                    "Tu incidencia «" . $incidencia["titulo"] . "» quedó registrada con el folio " . $incidencia["folio"] . ".",
                    "Tiempo estimado de atención: hasta el " . $vence . ". Adjuntamos tu ticket en PDF; consérvalo para garantía.",
                ],
                $enlace,
                "Ver el seguimiento"
            ),
            "Hola " . $incidencia["nombre"] . ",\n\nTu incidencia quedó registrada con el folio " . $incidencia["folio"]
                . ".\nTiempo estimado de atención: hasta el " . $vence . ".\nAdjuntamos tu ticket en PDF.\n\nSeguimiento: " . $enlace,
            [[
                "nombre" => "ticket_" . preg_replace('/[^A-Za-z0-9_-]/', "", $incidencia["folio"]) . ".pdf",
                "tipo" => "application/pdf",
                "contenido" => $pdf,
            ]]
        );

    } catch (Throwable $e) {

        error_log("Ticket por correo no enviado: " . $e->getMessage());
    }
}
