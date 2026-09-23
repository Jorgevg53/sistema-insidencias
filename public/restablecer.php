<?php

/*
 * Crear una nueva contraseña con un enlace de un solo uso:
 *   restablecer.php?token=...
 */

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Restablecimiento.php";

// Que el token no se envíe a otros sitios en el encabezado Referer.
header("Referrer-Policy: no-referrer");

$token = $_POST["token"] ?? $_GET["token"] ?? "";

$database = new Database();
$conn = $database->conectar();

$restablecimientoModel = new Restablecimiento($conn);

$restablecimiento = $restablecimientoModel->buscarVigente($token);

$error = "";

if ($restablecimiento && $_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["password"] ?? "";
    $confirmar = $_POST["password_confirmar"] ?? "";

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (strlen($password) < 8) {

        $error = "La contraseña debe tener al menos 8 caracteres.";

    } elseif ($password !== $confirmar) {

        $error = "Las contraseñas no coinciden.";

    } else {

        $conn->beginTransaction();

        if (!$restablecimientoModel->usar($restablecimiento, $password)) {

            $conn->rollBack();

            flash("error", "El enlace ya se usó o caducó. Solicita uno nuevo.");

            header("Location: recuperar.php");
            exit;
        }

        $conn->commit();

        /*
         * Si había una sesión abierta en este navegador, se cierra.
         */
        session_unset();
        session_regenerate_id(true);

        flash("exito", "Tu contraseña se actualizó. Ya puedes iniciar sesión.");

        header("Location: login.php");
        exit;
    }
}

$tituloPagina = "Nueva contraseña";

require_once "../app/views/auth/tarjeta_inicio.php";

?>

<?php if (!$restablecimiento): ?>

    <div class="error">
        El enlace no es válido, ya se usó o caducó.
    </div>

    <p><a href="recuperar.php">Solicitar un enlace nuevo</a></p>

<?php else: ?>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <p>Hola <strong><?= e($restablecimiento["nombre"]) ?></strong>, escribe tu nueva contraseña.</p>

    <form method="POST">

        <?= campoCsrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div>
            <label for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
        </div>

        <div>
            <label for="password_confirmar">Confirmar contraseña</label>
            <input type="password" id="password_confirmar" name="password_confirmar" minlength="8" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary">Guardar contraseña</button>

    </form>

    <p class="texto-suave" style="font-size: 0.85rem">
        Este enlace caduca el <?= e($restablecimiento["expira"]) ?> y solo funciona una vez.
    </p>

<?php endif; ?>

<?php require_once "../app/views/auth/tarjeta_fin.php"; ?>
