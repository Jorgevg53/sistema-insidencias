<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Usuario.php";
require_once "../app/helpers/carreras.php";

requerirSesion();

$database = new Database();
$conn = $database->conectar();

$usuarioModel = new Usuario($conn);

$usuario = $usuarioModel->buscarPorId($_SESSION["usuario_id"]);

$error = "";

$errorContacto = "";

/*
|--------------------------------------------------------------------------
| DATOS DE CONTACTO (aparecen en el ticket)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "contacto") {

    $carrera = trim($_POST["carrera"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");

    if (!verificarCsrf()) {

        $errorContacto = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (!carreraValida($carrera, $usuario["carrera"])) {

        $errorContacto = "Selecciona una carrera de la lista.";

    } elseif ($telefono !== "" && !preg_match('/^[0-9 +()-]{7,20}$/', $telefono)) {

        $errorContacto = "El teléfono solo puede tener números, espacios y los signos + ( ) - (de 7 a 20 caracteres).";

    } else {

        $usuarioModel->actualizarContacto(
            $usuario["id"],
            $carrera !== "" ? $carrera : null,
            $telefono !== "" ? $telefono : null
        );

        flash("exito", "Tus datos de contacto se actualizaron.");

        header("Location: perfil.php");
        exit;
    }

    $usuario["carrera"] = $carrera;
    $usuario["telefono"] = $telefono;
}

/*
|--------------------------------------------------------------------------
| CAMBIAR CONTRASEÑA
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "password") {

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

    </dl>

    <p class="texto-suave">Si tu nombre, correo o matrícula son incorrectos, solicita el cambio al Administrador.</p>

</section>

<section class="tarjeta">

    <h3>Datos de contacto</h3>

    <p class="texto-suave">Aparecen en el ticket de tus incidencias.</p>

    <?php if ($errorContacto): ?>
        <div class="alerta alerta-error"><?= e($errorContacto) ?></div>
    <?php endif; ?>

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="contacto">

        <div class="formulario-2col">

            <div>
                <label for="carrera">Carrera</label>
                <?= campoCarrera($usuario["carrera"]) ?>
            </div>

            <div>
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" value="<?= e($usuario["telefono"]) ?>">
            </div>

        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Guardar datos de contacto</button>
        </div>

    </form>

</section>

<section class="tarjeta">

    <h3>Cambiar contraseña</h3>

    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="password">

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
