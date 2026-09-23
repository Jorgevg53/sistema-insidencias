<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Catalogo.php";

/*
 * Módulo exclusivo del Administrador.
 */
requerirRol(["Administrador"]);

$tab = ($_GET["tab"] ?? "") === "prioridades" ? "prioridades" : "categorias";

$database = new Database();
$conn = $database->conectar();

$catalogo = new Catalogo($conn, $tab);

$error = "";

/*
 * Formulario: alta (sin ?editar) o edición (con ?editar=id).
 */
$editarId = $_GET["editar"] ?? "";
$enEdicion = null;

if ($editarId !== "") {

    $enEdicion = ctype_digit((string) $editarId) ? $catalogo->buscarPorId($editarId) : false;

    if (!$enEdicion) {
        flash("error", "El elemento no existe.");
        header("Location: catalogos.php?tab=" . $tab);
        exit;
    }
}

$datos = [
    "nombre" => $enEdicion["nombre"] ?? "",
    "descripcion" => $enEdicion["descripcion"] ?? "",
    "nivel" => $enEdicion["nivel"] ?? "",
    "dias_atencion" => $enEdicion["dias_atencion"] ?? "3"
];

/*
|--------------------------------------------------------------------------
| ACCIONES
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $id = $_POST["id"] ?? "";

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif ($accion === "guardar") {

        $datos = [
            "nombre" => $_POST["nombre"] ?? "",
            "descripcion" => $_POST["descripcion"] ?? "",
            "nivel" => $_POST["nivel"] ?? "",
            "dias_atencion" => $_POST["dias_atencion"] ?? ""
        ];

        $error = $catalogo->guardar($enEdicion ? $enEdicion["id"] : null, $datos);

        if (!$error) {
            flash("exito", $enEdicion ? "Cambios guardados." : "Elemento agregado.");
            header("Location: catalogos.php?tab=" . $tab);
            exit;
        }

    } elseif (in_array($accion, ["activar", "desactivar", "eliminar"], true)) {

        if (!ctype_digit((string) $id) || !$catalogo->buscarPorId($id)) {

            $error = "El elemento no existe.";

        } else {

            $error = $accion === "eliminar"
                ? $catalogo->eliminar($id)
                : $catalogo->cambiarActivo($id, $accion === "activar");

            if (!$error) {

                $mensajes = [
                    "activar" => "Elemento activado.",
                    "desactivar" => "Elemento desactivado. Ya no aparecerá al registrar incidencias.",
                    "eliminar" => "Elemento eliminado."
                ];

                flash("exito", $mensajes[$accion]);
                header("Location: catalogos.php?tab=" . $tab);
                exit;
            }
        }

    } else {

        $error = "Acción no válida.";
    }
}

$elementos = $catalogo->listar();

$tituloPagina = "Catálogos";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <div>
        <h1>Catálogos</h1>
        <span class="texto-suave">Opciones disponibles al registrar incidencias.</span>
    </div>
</div>

<nav class="pestanas">
    <a href="catalogos.php?tab=categorias" class="<?= $tab === "categorias" ? "activo" : "" ?>">Categorías</a>
    <a href="catalogos.php?tab=prioridades" class="<?= $tab === "prioridades" ? "activo" : "" ?>">Prioridades</a>
</nav>

<?php if ($error): ?>
    <div class="alerta alerta-error"><?= e($error) ?></div>
<?php endif; ?>

<section class="tarjeta">

    <h3>
        <?php if ($enEdicion): ?>
            Editar <?= $tab === "categorias" ? "categoría" : "prioridad" ?>: <?= e($enEdicion["nombre"]) ?>
        <?php else: ?>
            Nueva <?= $tab === "categorias" ? "categoría" : "prioridad" ?>
        <?php endif; ?>
    </h3>

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="guardar">

        <div class="formulario-2col">

            <div>
                <label for="nombre">Nombre <span class="requerido">*</span></label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    maxlength="<?= Catalogo::CATALOGOS[$tab]["largo_nombre"] ?>"
                    value="<?= e($datos["nombre"]) ?>"
                    required
                >
            </div>

            <?php if ($tab === "categorias"): ?>

                <div>
                    <label for="descripcion">Descripción</label>
                    <input
                        type="text"
                        id="descripcion"
                        name="descripcion"
                        maxlength="255"
                        value="<?= e($datos["descripcion"]) ?>"
                    >
                </div>

            <?php else: ?>

                <div>
                    <label for="nivel">Nivel <span class="requerido">*</span></label>
                    <input
                        type="number"
                        id="nivel"
                        name="nivel"
                        min="1"
                        max="99"
                        value="<?= e($datos["nivel"]) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="dias_atencion">Tiempo estimado de atención (días hábiles) <span class="requerido">*</span></label>
                    <input
                        type="number"
                        id="dias_atencion"
                        name="dias_atencion"
                        min="1"
                        max="60"
                        value="<?= e($datos["dias_atencion"]) ?>"
                        required
                    >
                </div>

            <?php endif; ?>

        </div>

        <?php if ($tab === "prioridades"): ?>
            <p class="texto-suave" style="margin: 0">
                El nivel define el orden: un número mayor es más urgente
                (las incidencias asignadas se ordenan por él). El tiempo de atención
                aparece en el ticket de cada incidencia.
            </p>
        <?php endif; ?>

        <div class="acciones">
            <button type="submit" class="btn btn-primary"><?= $enEdicion ? "Guardar cambios" : "Agregar" ?></button>
            <?php if ($enEdicion): ?>
                <a href="catalogos.php?tab=<?= $tab ?>" class="btn btn-secundario">Cancelar</a>
            <?php endif; ?>
        </div>

    </form>

</section>

<section class="tarjeta">

    <div class="tabla-contenedor">

        <table class="tabla">

            <thead>
                <tr>
                    <th>Nombre</th>
                    <th><?= $tab === "categorias" ? "Descripción" : "Nivel" ?></th>
                    <?php if ($tab === "prioridades"): ?>
                        <th>Tiempo de atención</th>
                    <?php endif; ?>
                    <th>Incidencias</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($elementos as $elemento): ?>

                    <tr>
                        <td>
                            <?php if ($tab === "prioridades"): ?>
                                <span class="badge <?= clasePrioridad($elemento["nombre"]) ?>"><?= e($elemento["nombre"]) ?></span>
                            <?php else: ?>
                                <?= e($elemento["nombre"]) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $tab === "categorias"
                                ? e($elemento["descripcion"] ?: "—")
                                : (int) $elemento["nivel"] ?>
                        </td>
                        <?php if ($tab === "prioridades"): ?>
                            <td>
                                <?= (int) $elemento["dias_atencion"] ?>
                                <?= (int) $elemento["dias_atencion"] === 1 ? "día hábil" : "días hábiles" ?>
                            </td>
                        <?php endif; ?>
                        <td><?= (int) $elemento["incidencias"] ?></td>
                        <td>
                            <?php if ($elemento["activo"]): ?>
                                <span class="badge badge-activo">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="acciones">

                                <a
                                    href="catalogos.php?tab=<?= $tab ?>&editar=<?= (int) $elemento["id"] ?>"
                                    class="btn btn-secundario btn-sm"
                                >Editar</a>

                                <form method="POST">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $elemento["id"] ?>">
                                    <input type="hidden" name="accion" value="<?= $elemento["activo"] ? "desactivar" : "activar" ?>">
                                    <button type="submit" class="btn btn-secundario btn-sm">
                                        <?= $elemento["activo"] ? "Desactivar" : "Activar" ?>
                                    </button>
                                </form>

                                <?php if ((int) $elemento["incidencias"] === 0): ?>
                                    <form
                                        method="POST"
                                        data-confirmar="¿Eliminar «<?= e($elemento["nombre"]) ?>»? Esta acción no se puede deshacer."
                                    >
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= (int) $elemento["id"] ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <button type="submit" class="btn btn-peligro btn-sm">Eliminar</button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <p class="texto-suave">
        Los elementos que ya se usan en incidencias no se pueden eliminar; desactívalos para que
        ya no aparezcan al registrar incidencias nuevas (las existentes los conservan).
    </p>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
