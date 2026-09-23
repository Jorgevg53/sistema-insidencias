<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";

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

    if (!verificarCsrf()) {

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

    } else {

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

            flash("exito", "Incidencia registrada correctamente. Folio: " . $folio);

            header("Location: detalle_incidencia.php?id=" . $conn->lastInsertId());
            exit;

        } catch (PDOException $e) {

            $error = "No se pudo registrar la incidencia.";

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

    <form method="POST" class="formulario">

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

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Registrar incidencia</button>
            <a href="dashboard.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
