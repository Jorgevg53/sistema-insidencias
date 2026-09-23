<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../app/config/database.php";

$error = "";
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = trim($_POST["titulo"] ?? "");
    $categoria_id = $_POST["categoria_id"] ?? "";
    $prioridad_id = $_POST["prioridad_id"] ?? "";
    $ubicacion = trim($_POST["ubicacion"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    if (
        empty($titulo) ||
        empty($categoria_id) ||
        empty($prioridad_id) ||
        empty($descripcion)
    ) {

        $error = "Los campos obligatorios deben completarse.";

    } else {

        try {

            $database = new Database();
            $conn = $database->conectar();

            /*
             * El estado inicial de toda incidencia
             * será "Pendiente".
             */
            $estado_id = 1;

            /*
             * Generamos un folio único.
             * Ejemplo: INC-20260922-001234
             */
            $folio = "INC-" . date("Ymd-His");

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
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
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
                $ubicacion
            ]);

            $mensaje = "Incidencia registrada correctamente. Folio: " . $folio;

            /*
             * Limpiamos los valores del formulario
             * después de registrar correctamente.
             */
            $titulo = "";
            $categoria_id = "";
            $prioridad_id = "";
            $ubicacion = "";
            $descripcion = "";

        } catch (PDOException $e) {

            $error = "No se pudo registrar la incidencia.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrar incidencia | TESCHI</title>

</head>

<body>

    <header>

        <h1>
            Sistema de Gestión de Incidencias
        </h1>

        <p>
            Departamento de Ciencias Básicas
        </p>

        <p>
            Tecnológico de Estudios Superiores de Chimalhuacán
        </p>

    </header>

    <hr>

    <main>

        <h2>
            Registrar nueva incidencia
        </h2>

        <p>
            Usuario:
            <strong>
                <?= htmlspecialchars(
                    $_SESSION["nombre"] . " " .
                    ($_SESSION["apellido_paterno"] ?? "")
                ) ?>
            </strong>
        </p>

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

        <form method="POST">

            <div>

                <label for="titulo">
                    Título de la incidencia
                </label>

                <br>

                <input
                    type="text"
                    id="titulo"
                    name="titulo"
                    maxlength="200"
                    value="<?= htmlspecialchars($titulo ?? "") ?>"
                    required
                >

            </div>

            <br>

            <div>

                <label for="categoria">
                    Categoría
                </label>

                <br>

                <select
                    id="categoria"
                    name="categoria_id"
                    required
                >

                    <option value="">
                        Selecciona una categoría
                    </option>

                    <option value="1">
                        Académica
                    </option>

                    <option value="2">
                        Administrativa
                    </option>

                    <option value="3">
                        Infraestructura
                    </option>

                    <option value="4">
                        Equipo de cómputo
                    </option>

                    <option value="5">
                        Software
                    </option>

                    <option value="6">
                        Redes
                    </option>

                    <option value="7">
                        Control Escolar
                    </option>

                    <option value="8">
                        Otra
                    </option>

                </select>

            </div>

            <br>

            <div>

                <label for="prioridad">
                    Prioridad
                </label>

                <br>

                <select
                    id="prioridad"
                    name="prioridad_id"
                    required
                >

                    <option value="">
                        Selecciona una prioridad
                    </option>

                    <option value="1">
                        Baja
                    </option>

                    <option value="2">
                        Media
                    </option>

                    <option value="3">
                        Alta
                    </option>

                    <option value="4">
                        Crítica
                    </option>

                </select>

            </div>

            <br>

            <div>

                <label for="ubicacion">
                    Ubicación
                </label>

                <br>

                <input
                    type="text"
                    id="ubicacion"
                    name="ubicacion"
                    maxlength="200"
                    value="<?= htmlspecialchars($ubicacion ?? "") ?>"
                    placeholder="Ej. Laboratorio de cómputo 1"
                >

            </div>

            <br>

            <div>

                <label for="descripcion">
                    Descripción de la incidencia
                </label>

                <br>

                <textarea
                    id="descripcion"
                    name="descripcion"
                    rows="6"
                    cols="50"
                    required
                ><?= htmlspecialchars($descripcion ?? "") ?></textarea>

            </div>

            <br>

            <button type="submit">
                Registrar incidencia
            </button>

        </form>

        <hr>

        <a href="dashboard.php">
            ← Regresar al Dashboard
        </a>

    </main>

</body>

</html>