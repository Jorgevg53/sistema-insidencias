<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION["nombre"];
$apellido = $_SESSION["apellido_paterno"] ?? "";
$correo = $_SESSION["correo"];
$rol = $_SESSION["rol"];

require_once "../app/config/database.php";

$pendientes = 0;
$en_proceso = 0;
$resueltas = 0;

try {

    $database = new Database();
    $conn = $database->conectar();

    $sql = "
        SELECT
            e.nombre AS estado,
            COUNT(i.id) AS total
        FROM incidencias i
        INNER JOIN estados_incidencia e
            ON i.estado_id = e.id
        WHERE i.usuario_id = ?
        GROUP BY e.id, e.nombre
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $_SESSION["usuario_id"]
    ]);

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados as $resultado) {

        if ($resultado["estado"] === "Pendiente") {
            $pendientes = $resultado["total"];
        }

        if ($resultado["estado"] === "En proceso") {
            $en_proceso = $resultado["total"];
        }

        if ($resultado["estado"] === "Resuelta") {
            $resueltas = $resultado["total"];
        }
    }

} catch (PDOException $e) {

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Sistema de Incidencias TESCHI</title>

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
            Dashboard
        </h2>

        <section>

            <h3>
                Bienvenido
            </h3>

            <p>
                <?= htmlspecialchars($nombre . " " . $apellido) ?>
            </p>

            <p>
                <strong>Correo:</strong>
                <?= htmlspecialchars($correo) ?>
            </p>

            <p>
                <strong>Rol:</strong>
                <?= htmlspecialchars($rol) ?>
            </p>

        </section>

        <hr>

        <section>

            <h3>
                Módulos del sistema
            </h3>

            <ul>

                <li>
                    <a href="incidencias.php">
                        Registrar incidencia
                    </a>
                </li>

                <li>
                    <a href="mis_incidencias.php">
                        Mis incidencias
                    </a>
                </li>

                <li>
                    Seguimiento de incidencias
                </li>

                <li>
                    Notificaciones
                </li>

                <li>
                    Reportes
                </li>

            </ul>

        </section>

        <hr>

        <section>

            <h3>
                Resumen de incidencias
            </h3>

           <p>
                Pendientes:
                <strong><?= $pendientes ?></strong>
            </p>

            <p>
                En proceso:
                <strong><?= $en_proceso ?></strong>
            </p>

            <p>
                Resueltas:
                <strong><?= $resueltas ?></strong>
            </p>

        </section>

        <hr>

        <p>

            <a href="logout.php">
                Cerrar sesión
            </a>

        </p>

    </main>

</body>

</html>