<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Usuario.php";
require_once "../app/models/Restablecimiento.php";
require_once "../app/helpers/correo.php";

/*
 * Alta (sin ?id) y edición (con ?id) de usuarios.
 * Módulo exclusivo del Administrador.
 */
requerirRol(["Administrador"]);

$database = new Database();
$conn = $database->conectar();

$usuarioModel = new Usuario($conn);

$roles = $usuarioModel->obtenerRoles();

$id = $_GET["id"] ?? "";
$esEdicion = $id !== "";

$error = "";

$datos = [
    "matricula" => "",
    "nombre" => "",
    "apellido_paterno" => "",
    "apellido_materno" => "",
    "correo" => "",
    "rol_id" => "",
    "departamento" => "",
    "telefono" => "",
    "password" => ""
];

if ($esEdicion) {

    $usuario = ctype_digit((string) $id) ? $usuarioModel->buscarPorId($id) : false;

    if (!$usuario) {
        flash("error", "El usuario no existe.");
        header("Location: usuarios.php");
        exit;
    }

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = $campo === "password" ? "" : (string) ($usuario[$campo] ?? "");
    }
}

/*
|--------------------------------------------------------------------------
| RESTABLECER CONTRASEÑA (enlace de un solo uso)
|--------------------------------------------------------------------------
*/

$restablecimientoModel = new Restablecimiento($conn);

$enlaceGenerado = "";

$accionEnlace = $_POST["accion"] ?? "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    in_array($accionEnlace, ["generar_enlace", "enviar_enlace"], true)
) {

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (!$esEdicion || !$usuario["activo"]) {

        $error = "Solo se puede restablecer la contraseña de un usuario activo.";

    } elseif ($accionEnlace === "enviar_enlace") {

        if (!correoHabilitado()) {

            $error = "El envío de correos no está configurado.";

        } else {

            try {

                $token = $restablecimientoModel->crearToken(
                    $usuario,
                    "correo",
                    Restablecimiento::MINUTOS_CORREO,
                    $_SESSION["usuario_id"],
                    Restablecimiento::ipCliente()
                );

                $enlace = Restablecimiento::enlace(configCorreo()["url_base"], $token);

                enviarCorreo(
                    $usuario["correo"],
                    "Restablecer tu contraseña",
                    "<p>Hola " . e($usuario["nombre"]) . ",</p>"
                        . "<p>El Administrador del Sistema de Incidencias TESCHI generó un enlace para que crees una nueva contraseña:</p>"
                        . "<p><a href=\"" . e($enlace) . "\">Crear una nueva contraseña</a></p>"
                        . "<p>Funciona una sola vez y caduca en " . Restablecimiento::MINUTOS_CORREO . " minutos.</p>",
                    "Hola " . $usuario["nombre"] . ",\n\n"
                        . "Crea una nueva contraseña con este enlace:\n" . $enlace . "\n\n"
                        . "Funciona una sola vez y caduca en " . Restablecimiento::MINUTOS_CORREO . " minutos."
                );

                flash("exito", "Se envió el enlace a " . $usuario["correo"] . ".");

                header("Location: usuario_form.php?id=" . $usuario["id"]);
                exit;

            } catch (RuntimeException $e) {

                $restablecimientoModel->anularPendientes($usuario["id"]);

                $error = "No se pudo enviar el correo: " . $e->getMessage();
            }
        }

    } else {

        $token = $restablecimientoModel->crearToken(
            $usuario,
            "administrador",
            Restablecimiento::MINUTOS_ADMINISTRADOR,
            $_SESSION["usuario_id"],
            Restablecimiento::ipCliente()
        );

        /*
         * El enlace se muestra solo en la pantalla del Administrador,
         * así que se puede armar con la dirección que él está usando.
         */
        $urlBase = configCorreo()["url_base"] ?: (
            (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" ? "https" : "http")
            . "://" . $_SERVER["HTTP_HOST"]
            . rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/")
        );

        $enlaceGenerado = Restablecimiento::enlace($urlBase, $token);
    }
}

$ultimaSolicitud = $esEdicion ? $restablecimientoModel->solicitudPendiente($usuario["id"]) : false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && $accionEnlace === "") {

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = $campo === "password"
            ? ($_POST[$campo] ?? "")
            : trim($_POST[$campo] ?? "");
    }

    $confirmar = $_POST["password_confirmar"] ?? "";

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif ($datos["nombre"] === "" || $datos["correo"] === "" || $datos["rol_id"] === "") {

        $error = "Nombre, correo y rol son obligatorios.";

    } elseif (!filter_var($datos["correo"], FILTER_VALIDATE_EMAIL)) {

        $error = "El correo electrónico no es válido.";

    } elseif (!isset($roles[$datos["rol_id"]])) {

        $error = "El rol seleccionado no es válido.";

    } elseif ($usuarioModel->existe("correo", $datos["correo"], $esEdicion ? $id : 0)) {

        $error = "Ya existe un usuario con ese correo.";

    } elseif (
        $datos["matricula"] !== "" &&
        $usuarioModel->existe("matricula", $datos["matricula"], $esEdicion ? $id : 0)
    ) {

        $error = "Ya existe un usuario con esa matrícula.";

    } elseif (!$esEdicion && $datos["password"] === "") {

        $error = "La contraseña es obligatoria para un usuario nuevo.";

    } elseif ($datos["password"] !== "" && strlen($datos["password"]) < 8) {

        $error = "La contraseña debe tener al menos 8 caracteres.";

    } elseif ($datos["password"] !== $confirmar) {

        $error = "Las contraseñas no coinciden.";

    } elseif (
        $esEdicion &&
        (int) $id === (int) $_SESSION["usuario_id"] &&
        $roles[$datos["rol_id"]] !== "Administrador"
    ) {

        $error = "No puedes quitarte a ti mismo el rol de Administrador.";

    } else {

        /*
         * Los campos opcionales vacíos se guardan como NULL
         * (la matrícula es UNIQUE y varios usuarios pueden no tenerla).
         */
        foreach (["matricula", "apellido_paterno", "apellido_materno", "departamento", "telefono"] as $campo) {
            if ($datos[$campo] === "") {
                $datos[$campo] = null;
            }
        }

        try {

            if ($esEdicion) {
                $usuarioModel->actualizar($id, $datos);

                /*
                 * Si el administrador se editó a sí mismo,
                 * se actualizan los datos mostrados en la sesión.
                 */
                if ((int) $id === (int) $_SESSION["usuario_id"]) {
                    $_SESSION["nombre"] = $datos["nombre"];
                    $_SESSION["apellido_paterno"] = $datos["apellido_paterno"];
                    $_SESSION["correo"] = $datos["correo"];
                }

                flash("exito", "Usuario actualizado correctamente.");
            } else {
                $usuarioModel->crear($datos);
                flash("exito", "Usuario creado correctamente.");
            }

            header("Location: usuarios.php");
            exit;

        } catch (PDOException $e) {

            $error = "No se pudo guardar el usuario.";

        }
    }
}

$tituloPagina = $esEdicion ? "Editar usuario" : "Nuevo usuario";
$paginaActual = "usuarios.php";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1><?= $esEdicion ? "Editar usuario" : "Nuevo usuario" ?></h1>
</div>

<section class="tarjeta">

    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>

        <div class="formulario-2col">

            <div>
                <label for="nombre">Nombre(s) <span class="requerido">*</span></label>
                <input type="text" id="nombre" name="nombre" maxlength="100" value="<?= e($datos["nombre"]) ?>" required>
            </div>

            <div>
                <label for="apellido_paterno">Apellido paterno</label>
                <input type="text" id="apellido_paterno" name="apellido_paterno" maxlength="100" value="<?= e($datos["apellido_paterno"]) ?>">
            </div>

            <div>
                <label for="apellido_materno">Apellido materno</label>
                <input type="text" id="apellido_materno" name="apellido_materno" maxlength="100" value="<?= e($datos["apellido_materno"]) ?>">
            </div>

            <div>
                <label for="matricula">Matrícula / No. de empleado</label>
                <input type="text" id="matricula" name="matricula" maxlength="30" value="<?= e($datos["matricula"]) ?>">
            </div>

            <div>
                <label for="correo">Correo electrónico <span class="requerido">*</span></label>
                <input type="email" id="correo" name="correo" maxlength="150" value="<?= e($datos["correo"]) ?>" required>
            </div>

            <div>
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" value="<?= e($datos["telefono"]) ?>">
            </div>

            <div>
                <label for="rol_id">Rol <span class="requerido">*</span></label>
                <select id="rol_id" name="rol_id" required>
                    <option value="">Selecciona un rol</option>
                    <?php foreach ($roles as $rolId => $nombre): ?>
                        <option value="<?= $rolId ?>" <?= (string) $rolId === $datos["rol_id"] ? "selected" : "" ?>>
                            <?= e($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="departamento">Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="150" value="<?= e($datos["departamento"]) ?>">
            </div>

        </div>

        <h3 style="margin-bottom: 0">Contraseña</h3>

        <?php if ($esEdicion): ?>
            <p class="texto-suave" style="margin: 0">Déjala en blanco para conservar la contraseña actual.</p>
        <?php endif; ?>

        <div class="formulario-2col">

            <div>
                <label for="password">
                    Contraseña <?php if (!$esEdicion): ?><span class="requerido">*</span><?php endif; ?>
                </label>
                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" <?= $esEdicion ? "" : "required" ?>>
            </div>

            <div>
                <label for="password_confirmar">Confirmar contraseña</label>
                <input type="password" id="password_confirmar" name="password_confirmar" minlength="8" autocomplete="new-password" <?= $esEdicion ? "" : "required" ?>>
            </div>

        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="usuarios.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>

</section>

<?php if ($esEdicion && (int) $usuario["id"] !== (int) $_SESSION["usuario_id"]): ?>

    <section class="tarjeta">

        <h3>Restablecer contraseña</h3>

        <?php if ($ultimaSolicitud): ?>
            <div class="alerta alerta-aviso">
                Este usuario solicitó restablecer su contraseña el <?= e($ultimaSolicitud) ?>.
            </div>
        <?php endif; ?>

        <?php if ($enlaceGenerado): ?>

            <div class="alerta alerta-exito">
                Enlace generado. Compártelo solo con <?= e($usuario["nombre"]) ?>: funciona una vez y
                caduca en <?= Restablecimiento::MINUTOS_ADMINISTRADOR / 60 ?> horas. No se volverá a mostrar.
            </div>

            <div class="copiar">
                <input type="text" id="enlace_restablecer" value="<?= e($enlaceGenerado) ?>" readonly>
                <button type="button" class="btn btn-secundario" data-copiar="#enlace_restablecer">Copiar</button>
            </div>

        <?php else: ?>

            <p class="texto-suave">
                Genera un enlace de un solo uso para que el usuario cree su propia contraseña,
                sin que tú tengas que conocerla. Los enlaces anteriores quedan anulados.
            </p>

        <?php endif; ?>

        <?php if ($usuario["activo"]): ?>

            <div class="acciones">

                <form method="POST">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="generar_enlace">
                    <button type="submit" class="btn btn-secundario">
                        <?= $enlaceGenerado ? "Generar otro enlace" : "Generar enlace" ?>
                    </button>
                </form>

                <?php if (correoHabilitado()): ?>
                    <form method="POST">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="enviar_enlace">
                        <button type="submit" class="btn btn-primary">Enviar enlace por correo</button>
                    </form>
                <?php endif; ?>

            </div>

        <?php else: ?>

            <p class="texto-suave">Activa al usuario para poder restablecer su contraseña.</p>

        <?php endif; ?>

    </section>

<?php endif; ?>

<?php require_once "../app/views/layouts/footer.php"; ?>
