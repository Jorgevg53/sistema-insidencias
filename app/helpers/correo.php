<?php

/*
|--------------------------------------------------------------------------
| ENVÍO DE CORREO POR SMTP
|--------------------------------------------------------------------------
| Cliente SMTP mínimo (sin librerías externas) compatible con Gmail,
| Outlook y la mayoría de proveedores: STARTTLS o SSL + AUTH LOGIN.
*/

function configCorreo()
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . "/../config/correo.php";
    }

    return $config;
}

function correoHabilitado()
{
    $config = configCorreo();

    return !empty($config["habilitado"]) && !empty($config["url_base"]) && !empty($config["remitente"]);
}

/*
 * Envía un correo HTML con alternativa en texto plano.
 * Lanza RuntimeException si algo falla.
 */
function enviarCorreo($para, $asunto, $html, $texto)
{
    $config = configCorreo();

    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Destinatario no válido.");
    }

    $remitente = $config["remitente"];

    $remoto = ($config["seguridad"] === "ssl" ? "ssl://" : "tcp://") . $config["host"] . ":" . (int) $config["puerto"];

    $contexto = stream_context_create([
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
            "peer_name" => $config["host"]
        ]
    ]);

    $socket = @stream_socket_client($remoto, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);

    if (!$socket) {
        throw new RuntimeException("No se pudo conectar al servidor de correo: $errstr ($errno)");
    }

    stream_set_timeout($socket, 15);

    /*
     * Lee una respuesta (puede tener varias líneas "250-...")
     * y verifica el código esperado.
     */
    $comando = function ($linea, array $esperados) use ($socket) {

        if ($linea !== null) {
            fwrite($socket, $linea . "\r\n");
        }

        $respuesta = "";

        while (($renglon = fgets($socket, 515)) !== false) {
            $respuesta .= $renglon;

            if (strlen($renglon) < 4 || $renglon[3] === " ") {
                break;
            }
        }

        if (!in_array((int) substr($respuesta, 0, 3), $esperados, true)) {
            throw new RuntimeException("El servidor de correo respondió: " . trim($respuesta));
        }

        return $respuesta;
    };

    try {

        $comando(null, [220]);

        $comando("EHLO localhost", [250]);

        if ($config["seguridad"] === "tls") {

            $comando("STARTTLS", [220]);

            $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;

            if (defined("STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT")) {
                $metodo |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            if (defined("STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT")) {
                $metodo |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $metodo)) {
                throw new RuntimeException("No se pudo iniciar la conexión segura (TLS).");
            }

            $comando("EHLO localhost", [250]);
        }

        if ($config["usuario"] !== "") {
            $comando("AUTH LOGIN", [334]);
            $comando(base64_encode($config["usuario"]), [334]);
            $comando(base64_encode($config["password"]), [235]);
        }

        $comando("MAIL FROM:<" . $remitente . ">", [250]);
        $comando("RCPT TO:<" . $para . ">", [250, 251]);
        $comando("DATA", [354]);

        $separador = "=_" . bin2hex(random_bytes(12));

        $codificar = function ($texto) {
            return "=?UTF-8?B?" . base64_encode($texto) . "?=";
        };

        $mensaje = implode("\r\n", [
            "Date: " . date(DATE_RFC2822),
            "From: " . $codificar($config["nombre_remitente"]) . " <" . $remitente . ">",
            "To: <" . $para . ">",
            "Subject: " . $codificar($asunto),
            "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . substr(strrchr($remitente, "@"), 1) . ">",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"" . $separador . "\"",
            "",
            "--" . $separador,
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            rtrim(chunk_split(base64_encode($texto))),
            "--" . $separador,
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            rtrim(chunk_split(base64_encode($html))),
            "--" . $separador . "--",
            ""
        ]);

        // Las líneas en base64 nunca empiezan con ".", así que no hay
        // que escapar puntos antes del terminador.
        $comando($mensaje . "\r\n.", [250]);

        $comando("QUIT", [221]);

    } finally {

        fclose($socket);
    }
}
