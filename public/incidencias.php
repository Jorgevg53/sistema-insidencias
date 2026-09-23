<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
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
     * cualquier cambio en las tablas se refleje aquí.
     */
    $categorias = $conn->query("
        SELECT id, nombre
        FROM categorias
        WHERE activo = 1
        ORDER BY id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    /*
     * Carrera y teléfono se proponen desde el perfil del usuario.
     */
    $stmtPerfil = $conn->prepare("SELECT carrera, telefono FROM usuarios WHERE id = ?");
    $stmtPerfil->execute([$_SESSION["usuario_id"]]);
    $perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

    $carrera = (string) ($perfil["carrera"] ?? "");
    $carreraPerfil = $carrera;
    $telefono_contacto = (string) ($perfil["telefono"] ?? "");

    $prioridades = $conn->query("
        SELECT id, nombre
        FROM prioridades
        WHERE activo = 1
        ORDER BY nivel
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {

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

        $error = "El título y la ubicación admiten máximo 200 caracteres.";

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
                    ubicacion,
                    carrera,
                    telefono_contacto
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $ubicacion !== "" ? $ubicacion : null,
                $carrera !== "" ? $carrera : null,
                $telefono_contacto !== "" ? $telefono_contacto : null
            ]);

            // Se lee antes de cualquier otra consulta (un UPDATE lo reinicia).
            $incidencia_id = $conn->lastInsertId();

            /*
             * Se actualiza el perfil para proponer los mismos datos
             * la próxima vez (solo si se capturaron).
             */
            $conn->prepare("
                UPDATE usuarios
                SET
                    carrera = COALESCE(?, carrera),
                    telefono = COALESCE(?, telefono)
                WHERE id = ?
            ")->execute([
                $carrera !== "" ? $carrera : null,
                $telefono_contacto !== "" ? $telefono_contacto : null,
                $_SESSION["usuario_id"]
            ]);

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

            // PDOException también es RuntimeException: los errores de la base
            // de datos nunca se muestran tal cual al usuario.
            $error = $e instanceof PDOException ? "No se pudo registrar la incidencia." : $e->getMessage();

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
