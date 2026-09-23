<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
require_once "../app/helpers/evidencias.php";

requerirSesion();

$error = "";

$titulo = "";
$categoria_id = "";
$prioridad_id = "";
$ubicacion = "";
$descripcion = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    /*
     * Los catálogos se leen de la base de datos para que
     * cualquier cambio en las tablas se refleje aquí.
     */
    $categorias = $conn->query("
        SELECT id, nombre
        FROM categorias
        WHERE activo = 1
        ORDER BY id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $prioridades = $conn->query("
        SELECT id, nombre
        FROM prioridades
        WHERE activo = 1
        ORDER BY nivel
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {

    die("Error en la base de datos: " . $e->getMessage());

}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = trim($_POST["titulo"] ?? "");
    $categoria_id = $_POST["categoria_id"] ?? "";
    $prioridad_id = $_POST["prioridad_id"] ?? "";
    $ubicacion = trim($_POST["ubicacion"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

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

        $error = "El título y la ubicación admiten máximo 200 caracteres.";

    } elseif ($errorArchivos) {

        $error = $errorArchivos;

    } else {

        $evidenciaModel = new Evidencia($conn);


        try {

            /*
             * El estado inicial de toda incidencia
             * será "Pendiente".
             */
            $estado_id = 1;

            /*
             * Folio único: fecha + sufijo aleatorio.
             * Ejemplo: INC-20260922-4F2A9C
             * (antes solo usaba la hora y dos registros en el
             * mismo segundo chocaban con la llave UNIQUE).
             */
            $folio = "INC-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));

            $sql = "
                INSERT INTO incidencias
                (
                    folio,
                    usuario_id,
                    categoria_id,
                    prioridad_id,
                    estado_id,
                    titulo,
                    descripcion,
                    ubicacion
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $conn->beginTransaction();

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $folio,
                $_SESSION["usuario_id"],
                $categoria_id,
                $prioridad_id,
                $estado_id,
                $titulo,
                $descripcion,
                $ubicacion !== "" ? $ubicacion : null
            ]);

            $incidencia_id = $conn->lastInsertId();

            /*
             * Primer evento del historial de seguimiento.
             */
            $incidenciaModel = new Incidencia($conn);

            $incidenciaModel->registrarHistorial(
                $incidencia_id,
                $_SESSION["usuario_id"],
                "registro",
                "Incidencia registrada",
                null,
                $estado_id
            );

            /*
             * Evidencias adjuntas al registrar (sin comentario).
             */
            $evidenciaModel->guardar($incidencia_id, null, $_SESSION["usuario_id"], $archivos);

            /*
             * Aviso a Administradores y Coordinadores.
             */
            $incidenciaModel->avisar(
                $incidenciaModel->obtenerGestores(),
                "nueva",
                "registró una nueva incidencia de prioridad " . $prioridades[$prioridad_id]
                    . ($archivos ? " con " . count($archivos) . (count($archivos) === 1 ? " evidencia" : " evidencias") : ""),
                $_SESSION["usuario_id"]
            );

            $incidenciaModel->enviarAvisos($incidencia_id, $_SESSION["usuario_id"]);

            $conn->commit();

            flash("exito", "Incidencia registrada correctamente. Folio: " . $folio);

            header("Location: detalle_incidencia.php?id=" . $incidencia_id);
            exit;

        } catch (PDOException | RuntimeException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $evidenciaModel->deshacer();

            $error = $e instanceof RuntimeException ? $e->getMessage() : "No se pudo registrar la incidencia.";

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
                    Categoría <span class="requerido">*</span>
                </label>
                <select id="categoria" name="categoria_id" required>
                    <option value="">Selecciona una categoría</option>
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

        <div>
            <label for="ubicacion">Ubicación</label>
            <input
                type="text"
                id="ubicacion"
                name="ubicacion"
                maxlength="200"
                value="<?= e($ubicacion) ?>"
                placeholder="Ej. Laboratorio de cómputo 1"
            >
        </div>

        <div>
            <label for="descripcion">
                Descripción de la incidencia <span class="requerido">*</span>
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
