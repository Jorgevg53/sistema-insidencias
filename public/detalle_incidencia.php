<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

if (
    $_SESSION["rol"] !== "Administrador" &&
    $_SESSION["rol"] !== "Coordinador"
) {
    header("Location: dashboard.php");
    exit;
}

require_once "../app/config/database.php";

$id = $_GET["id"] ?? "";

if (!is_numeric($id)) {
    die("Incidencia no válida.");
}

$mensaje = "";
$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO
    |--------------------------------------------------------------------------
    */

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $estado_id = $_POST["estado_id"] ?? "";

        if (!is_numeric($estado_id)) {

            $error = "El estado seleccionado no es válido.";

        } else {

            $sqlUpdate = "
                UPDATE incidencias
                SET estado_id = ?
                WHERE id = ?
            ";

            $stmtUpdate = $conn->prepare($sqlUpdate);

            $stmtUpdate->execute([
                $estado_id,
                $id
            ]);

            $mensaje = "El estado de la incidencia se actualizó correctamente.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER INCIDENCIA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            i.id,
            i.folio,
            i.titulo,
            i.descripcion,
            i.ubicacion,
            i.fecha_registro,
            i.fecha_actualizacion,
            i.fecha_cierre,

            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.correo,

            c.nombre AS categoria,
            p.nombre AS prioridad,
            e.nombre AS estado

        FROM incidencias i

        INNER JOIN usuarios u
            ON i.usuario_id = u.id

        INNER JOIN categorias c
            ON i.categoria_id = c.id

        INNER JOIN prioridades p
            ON i.prioridad_id = p.id

        INNER JOIN estados_incidencia e
            ON i.estado_id = e.id

        WHERE i.id = ?

        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);

    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        die("La incidencia no existe.");
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER ESTADOS
    |--------------------------------------------------------------------------
    */

    $sqlEstados = "
        SELECT
            id,
            nombre
        FROM estados_incidencia
        ORDER BY id
    ";

    $stmtEstados = $conn->query($sqlEstados);

    $estados = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Error en la base de datos: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Detalle de incidencia | TESCHI
    </title>

</head>

<body>

    <h1>
        Sistema de Gestión de Incidencias
    </h1>

    <p>
        Departamento de Ciencias Básicas
    </p>

    <p>
        Tecnológico de Estudios Superiores de Chimalhuacán
    </p>

    <hr>

    <h2>
        Detalle de incidencia
    </h2>

    <?php if (!empty($mensaje)): ?>

        <p>
            <strong>
                <?= htmlspecialchars($mensaje) ?>
            </strong>
        </p>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <p>
            <strong>
                <?= htmlspecialchars($error) ?>
            </strong>
        </p>

    <?php endif; ?>


    <p>
        <strong>Folio:</strong>
        <?= htmlspecialchars($incidencia["folio"]) ?>
    </p>


    <p>
        <strong>Título:</strong>
        <?= htmlspecialchars($incidencia["titulo"]) ?>
    </p>


    <p>
        <strong>Usuario que reportó:</strong>

        <?= htmlspecialchars(
            $incidencia["nombre"] . " " .
            $incidencia["apellido_paterno"]
        ) ?>
    </p>


    <p>
        <strong>Correo:</strong>
        <?= htmlspecialchars($incidencia["correo"]) ?>
    </p>


    <p>
        <strong>Categoría:</strong>
        <?= htmlspecialchars($incidencia["categoria"]) ?>
    </p>


    <p>
        <strong>Prioridad:</strong>
        <?= htmlspecialchars($incidencia["prioridad"]) ?>
    </p>


    <p>
        <strong>Estado actual:</strong>

        <strong>
            <?= htmlspecialchars($incidencia["estado"]) ?>
        </strong>

    </p>


    <hr>


    <h3>
        Gestionar estado
    </h3>


    <form method="POST">

        <label for="estado_id">
            Nuevo estado:
        </label>

        <select
            name="estado_id"
            id="estado_id"
            required
        >

                        <?php foreach ($estados as $estado): ?>

                <option
                    value="<?= $estado["id"] ?>"
                    <?= $estado["nombre"] === $incidencia["estado"]
                        ? "selected"
                        : "" ?>
                >

                    <?= htmlspecialchars($estado["nombre"]) ?>

                </option>

            <?php endforeach; ?>>

        </select>


        <button type="submit">
            Guardar cambios
        </button>

    </form>


    <hr>


    <p>
        <strong>Ubicación:</strong>
        <?= htmlspecialchars(
            $incidencia["ubicacion"] ?: "No especificada"
        ) ?>
    </p>


    <p>
        <strong>Descripción:</strong>
    </p>

    <p>
        <?= nl2br(
            htmlspecialchars($incidencia["descripcion"])
        ) ?>
    </p>


    <p>
        <strong>Fecha de registro:</strong>
        <?= htmlspecialchars($incidencia["fecha_registro"]) ?>
    </p>


    <p>
        <strong>Última actualización:</strong>
        <?= htmlspecialchars($incidencia["fecha_actualizacion"]) ?>
    </p>


    <?php if (!empty($incidencia["fecha_cierre"])): ?>

        <p>
            <strong>Fecha de cierre:</strong>
            <?= htmlspecialchars($incidencia["fecha_cierre"]) ?>
        </p>

    <?php endif; ?>


    <br>


    <a href="todas_incidencias.php">
        ← Regresar a todas las incidencias
    </a>

</body>

</html>