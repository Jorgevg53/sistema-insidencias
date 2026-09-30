<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
require_once "../app/models/Usuario.php";
require_once "../app/helpers/evidencias.php";
require_once "../app/helpers/carreras.php";

requerirSesion();

$error = "";

$titulo = "";
$categoria_id = "";
$prioridad_id = "";
$ubicacion = "";
$descripcion = "";
$carrera = "";
$telefono_contacto = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    /*
     * Los catálogos se leen de la base de datos para que
     * cualquier cambio en las colecciones se refleje aquí.
     */
    $categorias = [];
    foreach ($conn->getCollection("categorias")->find(["activo" => true], ["sort" => ["_id" => 1]]) as $c) {
        $categorias[(int) $c["_id"]] = $c["nombre"];
    }

    /*
     * Carrera y teléfono se proponen desde el perfil del usuario.
     */
    $perfil = $conn->getCollection("usuarios")->findOne(
        ["_id" => (int) $_SESSION["usuario_id"]],
        ["projection" => ["carrera" => 1, "telefono" => 1]]
    );

    $carrera = (string) ($perfil["carrera"] ?? "");
    $carreraPerfil = $carrera;
    $telefono_contacto = (string) ($perfil["telefono"] ?? "");
    $telefonoPerfil = $telefono_contacto;

    $prioridades = [];
    foreach ($conn->getCollection("prioridades")->find(["activo" => true], ["sort" => ["nivel" => 1]]) as $p) {
        $prioridades[(int) $p["_id"]] = $p["nombre"];
    }

} catch (Exception $e) {

    error_log("Error en la base de datos: " . $e->getMessage());

    die("Ocurrió un error al consultar la base de datos. Intenta de nuevo más tarde.");

}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = trim($_POST["titulo"] ?? "");
    $categoria_id = $_POST["categoria_id"] ?? "";
    $prioridad_id = $_POST["prioridad_id"] ?? "";
    $ubicacion = trim($_POST["ubicacion"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $carrera = trim($_POST["carrera"] ?? "");
    $telefono_contacto = trim($_POST["telefono_contacto"] ?? "");

    [$archivos, $errorArchivos] = Evidencia::validarSubida();

    if (peticionDemasiadoGrande()) {

        $error = mensajePeticionDemasiadoGrande();

    } elseif (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (
        $titulo === "" ||
        $descripcion === "" ||
        !isset($categorias[$categoria_id]) ||
        !isset($prioridades[$prioridad_id])
    ) {

        $error = "Los campos obligatorios deben completarse.";

    } elseif (mb_strlen($titulo) > 200 || mb_strlen($ubicacion) > 200) {

        $error = "El título y la materia/grupo admiten máximo 200 caracteres.";

    } elseif (!carreraValida($carrera, $carreraPerfil)) {

        $error = "Selecciona una carrera de la lista.";

    } elseif ($telefono_contacto !== "" && !preg_match('/^[0-9 +()-]{7,20}$/', $telefono_contacto)) {

        $error = "El teléfono solo puede tener números, espacios y los signos + ( ) - (de 7 a 20 caracteres).";

    } elseif ($errorArchivos) {

        $error = $errorArchivos;

    } else {

        $evidenciaModel = new Evidencia($conn);

        try {

            /*
             * Folio único: fecha + sufijo aleatorio. Ejemplo: INC-20260922-4F2A9C
             */
            $folio = "INC-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));

            $incidenciaModel = new Incidencia($conn);

            /*
             * La incidencia se crea como un solo documento, con su primer
             * evento de historial embebido y estado inicial "Pendiente".
             */
            $incidencia_id = $incidenciaModel->crear([
                "folio" => $folio,
                "usuario_id" => $_SESSION["usuario_id"],
                "categoria_id" => $categoria_id,
                "prioridad_id" => $prioridad_id,
                "titulo" => $titulo,
                "descripcion" => $descripcion,
                "ubicacion" => $ubicacion,
                "carrera" => $carrera,
                "telefono_contacto" => $telefono_contacto,
            ]);

            /*
             * Guarda los datos de contacto en el perfil para proponerlos
             * la próxima vez (solo si se capturaron).
             */
            if ($carrera !== "" || $telefono_contacto !== "") {
                (new Usuario($conn))->actualizarContacto(
                    $_SESSION["usuario_id"],
                    $carrera !== "" ? $carrera : $carreraPerfil,
                    $telefono_contacto !== "" ? $telefono_contacto : $telefonoPerfil
                );
            }

            /*
             * Evidencias adjuntas al registrar (sin comentario).
             */
            $evidenciaModel->guardar($incidencia_id, null, $_SESSION["usuario_id"], $archivos);

            /*
             * Aviso a los Administradores.
             */
            $incidenciaModel->avisar(
                $incidenciaModel->obtenerGestores(),
                "nueva",
                "registró una nueva incidencia de prioridad " . $prioridades[$prioridad_id]
                    . ($archivos ? " con " . count($archivos) . (count($archivos) === 1 ? " evidencia" : " evidencias") : ""),
                $_SESSION["usuario_id"]
            );

            $incidenciaModel->enviarAvisos($incidencia_id, $_SESSION["usuario_id"]);

            flash("exito", "Incidencia registrada correctamente. Folio: " . $folio);

            header("Location: detalle_incidencia.php?id=" . $incidencia_id);
            exit;

        } catch (Exception $e) {

            $evidenciaModel->deshacer();

            // Los errores de la base de datos nunca se muestran tal cual;
            // los de las evidencias (RuntimeException propia) sí son útiles.
            $error = ($e instanceof RuntimeException && !($e instanceof MongoDB\Driver\Exception\Exception))
                ? $e->getMessage()
                : "No se pudo registrar la incidencia.";

        }
    }
}

$tituloPagina = "Registrar incidencia";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1>Registrar nueva incidencia</h1>
</div>

<section class="tarjeta">

    <div class="alerta alerta-exito">
        Las incidencias se contestan en <?= Incidencia::DIAS_MIN ?> a <?= Incidencia::DIAS_MAX ?> días hábiles.
        Al registrarla se genera tu ticket y podrás seguir su avance desde "Mis incidencias".
    </div>

    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="formulario" enctype="multipart/form-data">

        <?= campoCsrf() ?>

        <div>
            <label for="titulo">
                Título de la incidencia <span class="requerido">*</span>
            </label>
            <input
                type="text"
                id="titulo"
                name="titulo"
                maxlength="200"
                value="<?= e($titulo) ?>"
                required
            >
        </div>

        <div class="formulario-2col">

            <div>
                <label for="categoria">
                    Tipo de incidencia <span class="requerido">*</span>
                </label>
                <select id="categoria" name="categoria_id" required>
                    <option value="">Selecciona el tipo de incidencia</option>
                    <?php foreach ($categorias as $id => $nombre): ?>
                        <option
                            value="<?= $id ?>"
                            <?= (string) $id === (string) $categoria_id ? "selected" : "" ?>
                        >
                            <?= e($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="prioridad">
                    Prioridad <span class="requerido">*</span>
                </label>
                <select id="prioridad" name="prioridad_id" required>
                    <option value="">Selecciona una prioridad</option>
                    <?php foreach ($prioridades as $id => $nombre): ?>
                        <option
                            value="<?= $id ?>"
                            <?= (string) $id === (string) $prioridad_id ? "selected" : "" ?>
                        >
                            <?= e($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <div class="formulario-2col">

            <div>
                <label for="carrera">Carrera</label>
                <?= campoCarrera($carrera) ?>
            </div>

            <div>
                <label for="telefono_contacto">Teléfono de contacto</label>
                <input
                    type="tel"
                    id="telefono_contacto"
                    name="telefono_contacto"
                    maxlength="20"
                    value="<?= e($telefono_contacto) ?>"
                    placeholder="Ej. 55 1234 5678"
                >
            </div>

        </div>

        <div>
            <label for="ubicacion">Materia y grupo</label>
            <input
                type="text"
                id="ubicacion"
                name="ubicacion"
                maxlength="200"
                value="<?= e($ubicacion) ?>"
                placeholder="Ej. Cálculo Diferencial · Grupo 1201"
            >
        </div>

        <div>
            <label for="descripcion">
                Descripción (alumno, periodo, calificación afectada) <span class="requerido">*</span>
            </label>
            <textarea
                id="descripcion"
                name="descripcion"
                rows="6"
                required
            ><?= e($descripcion) ?></textarea>
        </div>

        <?= campoEvidencias() ?>

        <?php if ($error && !empty($_FILES["evidencias"]["name"][0])): ?>
            <p class="texto-suave" style="margin: 0">Por seguridad, vuelve a seleccionar los archivos.</p>
        <?php endif; ?>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Registrar incidencia</button>
            <a href="dashboard.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
