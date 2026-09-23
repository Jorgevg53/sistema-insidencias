<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Usuario.php";

requerirSesion();

$database = new Database();
$conn = $database->conectar();

$usuarioModel = new Usuario($conn);

$usuario = $usuarioModel->buscarPorId($_SESSION["usuario_id"]);

$error = "";

/*
|--------------------------------------------------------------------------
| CAMBIAR CONTRASEÑA
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $actual = $_POST["password_actual"] ?? "";
    $nueva = $_POST["password_nueva"] ?? "";
    $confirmar = $_POST["password_confirmar"] ?? "";

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (!password_verify($actual, $usuario["password"])) {

        $error = "La contraseña actual es incorrecta.";

    } elseif (strlen($nueva) < 8) {

        $error = "La nueva contraseña debe tener al menos 8 caracteres.";

    } elseif ($nueva !== $confirmar) {

        $error = "Las contraseñas no coinciden.";

    } else {

        $usuarioModel->cambiarPassword($usuario["id"], $nueva);

        flash("exito", "Tu contraseña se actualizó correctamente.");

        header("Location: perfil.php");
        exit;
    }
}

$tituloPagina = "Mi perfil";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1>Mi perfil</h1>
</div>

<section class="tarjeta">

    <dl class="detalle-grid">

        <div>
            <dt>Nombre</dt>
            <dd>
                <?= e(trim(
                    $usuario["nombre"] . " " .
                    $usuario["apellido_paterno"] . " " .
                    $usuario["apellido_materno"]
                )) ?>
            </dd>
        </div>

        <div>
            <dt>Correo</dt>
            <dd><?= e($usuario["correo"]) ?></dd>
        </div>

        <div>
            <dt>Rol</dt>
            <dd><?= e($usuario["rol"]) ?></dd>
        </div>

        <div>
            <dt>Matrícula</dt>
            <dd><?= e($usuario["matricula"] ?: "—") ?></dd>
        </div>

        <div>
            <dt>Departamento</dt>
            <dd><?= e($usuario["departamento"] ?: "—") ?></dd>
        </div>

        <div>
            <dt>Teléfono</dt>
            <dd><?= e($usuario["telefono"] ?: "—") ?></dd>
        </div>

    </dl>

    <p class="texto-suave">Si algún dato es incorrecto, solicita el cambio al Administrador.</p>

</section>

<section class="tarjeta">

    <h3>Cambiar contraseña</h3>

    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>

        <div>
            <label for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual" autocomplete="current-password" required>
        </div>

        <div class="formulario-2col">

            <div>
                <label for="password_nueva">Nueva contraseña</label>
                <input type="password" id="password_nueva" name="password_nueva" minlength="8" autocomplete="new-password" required>
            </div>

            <div>
                <label for="password_confirmar">Confirmar nueva contraseña</label>
                <input type="password" id="password_confirmar" name="password_confirmar" minlength="8" autocomplete="new-password" required>
            </div>

        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
        </div>

    </form>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
