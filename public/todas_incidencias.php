<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

/*
 * Por ahora permitimos únicamente
 * Administrador y Coordinador.
 */
if (
    $_SESSION["rol"] !== "Administrador" &&
    $_SESSION["rol"] !== "Coordinador"
) {
    header("Location: dashboard.php");
    exit;
}

require_once "../app/config/database.php";

$incidencias = [];
$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    $sql = "
        SELECT

            i.id,
            i.folio,
            i.titulo,
            i.descripcion,
            i.ubicacion,
            i.fecha_registro,

            CONCAT(
                u.nombre,
                ' ',
                COALESCE(u.apellido_paterno, '')
            ) AS usuario,

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

        ORDER BY i.fecha_registro DESC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute();

    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = "No se pudieron consultar las incidencias.";

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
        Todas las incidencias | TESCHI
    </title>

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
            Todas las incidencias
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

        <p>
            Rol:
            <strong>
                <?= htmlspecialchars($_SESSION["rol"]) ?>
            </strong>
        </p>

        <?php if (!empty($error)): ?>

            <p>
                <strong>
                    <?= htmlspecialchars($error) ?>
                </strong>
            </p>

        <?php elseif (empty($incidencias)): ?>

            <p>
                No existen incidencias registradas.
            </p>

        <?php else: ?>

            <table
                border="1"
                cellpadding="8"
                cellspacing="0"
            >

                <thead>
                    <th>
    Acción


                    <tr>

                        <th>Folio</th>

                        <th>Título</th>

                        <th>Usuario</th>

                        <th>Categoría</th>

                        <th>Prioridad</th>

                        <th>Estado</th>

                        <th>Fecha</th>

                    </tr>
</th>
                </thead>

                <tbody>

                    <?php foreach ($incidencias as $incidencia): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["folio"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["titulo"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["usuario"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["categoria"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["prioridad"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["estado"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incidencia["fecha_registro"]
                                ) ?>
                            </td>
                            <td>
                                <a href="detalle_incidencia.php?id=<?= $incidencia["id"] ?>">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

        <hr>

        <p>

            <a href="dashboard.php">
                ← Regresar al Dashboard
            </a>

        </p>

    </main>

</body>

</html>