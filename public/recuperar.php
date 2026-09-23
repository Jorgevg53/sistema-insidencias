<?php

/*
 * "¿Olvidaste tu contraseña?"
 *
 * - Con correo configurado: se envía un enlace de un solo uso (60 min).
 * - Sin correo: se avisa a los Administradores para que generen el enlace.
 *
 * La respuesta es la misma exista o no el correo, para no revelar
 * qué cuentas están registradas.
 */

require_once "../app/helpers/auth.php";
require_once "../app/helpers/correo.php";
require_once "../app/config/database.php";
require_once "../app/models/Usuario.php";
require_once "../app/models/Restablecimiento.php";
require_once "../app/models/Notificacion.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$enviado = false;
$correo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "Escribe un correo electrónico válido.";

    } else {

        $database = new Database();
        $conn = $database->conectar();

        $restablecimiento = new Restablecimiento($conn);

        $ip = Restablecimiento::ipCliente();

        if ($restablecimiento->limiteExcedido($correo, $ip)) {

            $error = "Se hicieron demasiadas solicitudes. Intenta de nuevo en una hora.";

        } else {

            $usuario = (new Usuario($conn))->buscarPorCorreo($correo);

            $avisarAdministrador = true;

            if ($usuario && correoHabilitado()) {

                try {

                    $token = $restablecimiento->crearToken(
                        $usuario,
                        "correo",
                        Restablecimiento::MINUTOS_CORREO,
                        null,
                        $ip
                    );

                    $enlace = Restablecimiento::enlace(configCorreo()["url_base"], $token);

                    enviarCorreo(
                        $usuario["correo"],
                        "Restablecer tu contraseña",
                        "<p>Hola " . e($usuario["nombre"]) . ",</p>"
                            . "<p>Recibimos una solicitud para restablecer tu contraseña del Sistema de Incidencias TESCHI.</p>"
                            . "<p><a href=\"" . e($enlace) . "\">Crear una nueva contraseña</a></p>"
                            . "<p>El enlace funciona una sola vez y caduca en " . Restablecimiento::MINUTOS_CORREO . " minutos.</p>"
                            . "<p>Si no lo solicitaste, ignora este mensaje: tu contraseña no cambiará.</p>",
                        "Hola " . $usuario["nombre"] . ",\n\n"
                            . "Para crear una nueva contraseña abre este enlace:\n" . $enlace . "\n\n"
                            . "Funciona una sola vez y caduca en " . Restablecimiento::MINUTOS_CORREO . " minutos.\n"
                            . "Si no lo solicitaste, ignora este mensaje."
                    );

                    $avisarAdministrador = false;

                } catch (RuntimeException $e) {

                    // Si el correo falla, se anula el enlace y se avisa al Administrador.
                    error_log("Recuperar contraseña: " . $e->getMessage());

                    $restablecimiento->anularPendientes($usuario["id"]);
                }
            }

            if ($avisarAdministrador) {

                $restablecimiento->registrarSolicitud($usuario ? $usuario["id"] : null, $correo, $ip);

                if ($usuario) {

                    $notificacionModel = new Notificacion($conn);

                    $administradores = $conn->query("
                        SELECT u.id
                        FROM usuarios u
                        INNER JOIN roles r ON u.rol_id = r.id
                        WHERE u.activo = 1
                        AND r.nombre = 'Administrador'
                    ")->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($administradores as $administrador_id) {
                        $notificacionModel->crear(
                            $administrador_id,
                            $usuario["id"],
                            null,
                            "password",
                            trim($usuario["nombre"] . " " . $usuario["apellido_paterno"])
                                . " (" . $usuario["correo"] . ") solicitó restablecer su contraseña."
                        );
                    }
                }
            }

            $enviado = true;
        }
    }
}

$tituloPagina = "Recuperar contraseña";

require_once "../app/views/auth/tarjeta_inicio.php";

?>

<?php if ($enviado): ?>

    <div class="alerta alerta-exito">
        <?php if (correoHabilitado()): ?>
            Si el correo está registrado, te enviamos un enlace para crear una nueva contraseña.
            Revisa tu bandeja de entrada (y la carpeta de spam). Caduca en <?= Restablecimiento::MINUTOS_CORREO ?> minutos.
        <?php else: ?>
            Si el correo está registrado, avisamos al Administrador del sistema. Te hará llegar un
            enlace para crear una nueva contraseña.
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <p>Escribe el correo con el que inicias sesión.</p>

    <form method="POST">

        <?= campoCsrf() ?>

        <div>
            <label for="correo">Correo electrónico</label>
            <input type="email" id="correo" name="correo" value="<?= e($correo) ?>" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary">Solicitar enlace</button>

    </form>

<?php endif; ?>

<?php require_once "../app/views/auth/tarjeta_fin.php"; ?>
