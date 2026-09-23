<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Usuario.php";

/*
 * Módulo exclusivo del Administrador.
 */
requerirRol(["Administrador"]);

$database = new Database();
$conn = $database->conectar();

$usuarioModel = new Usuario($conn);

/*
|--------------------------------------------------------------------------
| ACTIVAR / DESACTIVAR USUARIO
|--------------------------------------------------------------------------
| No se eliminan usuarios porque tienen incidencias relacionadas;
| en su lugar se desactivan y ya no pueden iniciar sesión.
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $activo = ($_POST["activo"] ?? "") === "1";

    if (!verificarCsrf()) {

        flash("error", "La sesión del formulario expiró. Intenta de nuevo.");

    } elseif (!ctype_digit((string) $id) || !$usuarioModel->buscarPorId($id)) {

        flash("error", "El usuario no existe.");

    } elseif ((int) $id === (int) $_SESSION["usuario_id"]) {

        flash("error", "No puedes desactivar tu propia cuenta.");

    } else {

        $usuarioModel->cambiarActivo($id, $activo);

        flash("exito", $activo ? "Usuario activado." : "Usuario desactivado.");
    }

    header("Location: usuarios.php?" . http_build_query($_GET));
    exit;
}

/*
|--------------------------------------------------------------------------
| LISTADO CON FILTROS
|--------------------------------------------------------------------------
*/

$roles = $usuarioModel->obtenerRoles();

$filtro_texto = trim($_GET["q"] ?? "");
$filtro_rol = $_GET["rol_id"] ?? "";
$filtro_activo = $_GET["activo"] ?? "";

$usuarios = $usuarioModel->listar(
    $filtro_texto,
    isset($roles[$filtro_rol]) ? $filtro_rol : "",
    in_array($filtro_activo, ["0", "1"], true) ? $filtro_activo : ""
);

$tituloPagina = "Usuarios";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1>Usuarios</h1>
    <a href="usuario_form.php" class="btn btn-primary">+ Nuevo usuario</a>
</div>

<section class="tarjeta">

    <form method="GET" class="filtros">

        <div>
            <label for="q">Buscar</label>
            <input
                type="search"
                id="q"
                name="q"
                value="<?= e($filtro_texto) ?>"
                placeholder="Nombre, correo o matrícula"
            >
        </div>

        <div>
            <label for="rol_id">Rol</label>
            <select id="rol_id" name="rol_id">
                <option value="">Todos</option>
                <?php foreach ($roles as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtro_rol ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="activo">Estado</label>
            <select id="activo" name="activo">
                <option value="">Todos</option>
                <option value="1" <?= $filtro_activo === "1" ? "selected" : "" ?>>Activos</option>
                <option value="0" <?= $filtro_activo === "0" ? "selected" : "" ?>>Inactivos</option>
            </select>
        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="usuarios.php" class="btn btn-secundario">Limpiar</a>
        </div>

    </form>

</section>

<section class="tarjeta">

    <?php if (empty($usuarios)): ?>

        <p>No se encontraron usuarios.</p>

    <?php else: ?>

        <p class="texto-suave"><?= count($usuarios) ?> usuario(s) encontrados.</p>

        <div class="tabla-contenedor">

            <table class="tabla">

                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Matrícula</th>
                        <th>Rol</th>
                        <th>Departamento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($usuarios as $usuario): ?>

                        <tr>
                            <td>
                                <?= e(trim(
                                    $usuario["nombre"] . " " .
                                    $usuario["apellido_paterno"] . " " .
                                    $usuario["apellido_materno"]
                                )) ?>
                            </td>
                            <td><?= e($usuario["correo"]) ?></td>
                            <td><?= e($usuario["matricula"] ?: "—") ?></td>
                            <td><?= e($usuario["rol"]) ?></td>
                            <td><?= e($usuario["departamento"] ?: "—") ?></td>
                            <td>
                                <?php if ($usuario["activo"]): ?>
                                    <span class="badge badge-activo">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones">

                                    <a
                                        href="usuario_form.php?id=<?= (int) $usuario["id"] ?>"
                                        class="btn btn-secundario btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <?php if ((int) $usuario["id"] !== (int) $_SESSION["usuario_id"]): ?>

                                        <form
                                            method="POST"
                                            data-confirmar="<?= $usuario["activo"]
                                                ? "¿Desactivar a este usuario? Ya no podrá iniciar sesión."
                                                : "¿Activar a este usuario?" ?>"
                                        >
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="id" value="<?= (int) $usuario["id"] ?>">
                                            <input type="hidden" name="activo" value="<?= $usuario["activo"] ? "0" : "1" ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $usuario["activo"] ? "btn-peligro" : "btn-secundario" ?>"
                                            >
                                                <?= $usuario["activo"] ? "Desactivar" : "Activar" ?>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
