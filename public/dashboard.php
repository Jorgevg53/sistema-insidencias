<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";

requerirSesion();

$estados = [];
$total = 0;
$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    /*
     * Administrador y Coordinador ven el resumen de todas
     * las incidencias; los demás roles solo las propias.
     */
    $sql = "
        SELECT
            e.nombre AS estado,
            COUNT(i.id) AS total
        FROM estados_incidencia e
        LEFT JOIN incidencias i
            ON i.estado_id = e.id
            " . (esGestor() ? "" : "AND i.usuario_id = ?") . "
        GROUP BY e.id, e.nombre
        ORDER BY e.id
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute(esGestor() ? [] : [$_SESSION["usuario_id"]]);

    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($estados as $estado) {
        $total += $estado["total"];
    }

} catch (PDOException $e) {

    $error = "No se pudo obtener el resumen de incidencias.";

}

$tituloPagina = "Dashboard";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">

    <div>
        <h1>Bienvenido, <?= e($_SESSION["nombre"]) ?></h1>
        <span class="texto-suave">
            <?= e($_SESSION["correo"]) ?> · <?= e($_SESSION["rol"]) ?>
        </span>
    </div>

    <a href="incidencias.php" class="btn btn-primary">
        + Registrar incidencia
    </a>

</div>

<section class="tarjeta">

    <h3>
        <?= esGestor() ? "Resumen general de incidencias" : "Resumen de mis incidencias" ?>
    </h3>

    <?php if ($error): ?>

        <div class="alerta alerta-error"><?= e($error) ?></div>

    <?php else: ?>

        <div class="grid-resumen">

            <div class="resumen-item">
                <span class="numero"><?= $total ?></span>
                <span class="etiqueta">Total</span>
            </div>

            <?php foreach ($estados as $estado): ?>

                <div class="resumen-item">
                    <span class="numero"><?= (int) $estado["total"] ?></span>
                    <span class="badge <?= claseEstado($estado["estado"]) ?>">
                        <?= e($estado["estado"]) ?>
                    </span>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<section class="tarjeta">

    <h3>Módulos del sistema</h3>

    <div class="grid-modulos">

        <a class="modulo" href="incidencias.php">
            <strong>Registrar incidencia</strong>
            Reporta un nuevo problema o situación.
        </a>

        <a class="modulo" href="mis_incidencias.php">
            <strong>Mis incidencias</strong>
            Consulta el estado de tus reportes.
        </a>

        <?php if (tieneRol("Administrador", "Coordinador", "Docente", "Administrativo")): ?>

            <a class="modulo" href="asignadas.php">
                <strong>Asignadas a mí</strong>
                Atiende las incidencias que te asignaron.
            </a>

        <?php endif; ?>

        <?php if (esGestor()): ?>

            <a class="modulo" href="todas_incidencias.php">
                <strong>Todas las incidencias</strong>
                Revisa, filtra y cambia el estado de los reportes.
            </a>

        <?php endif; ?>

        <?php if (tieneRol("Administrador")): ?>

            <a class="modulo" href="usuarios.php">
                <strong>Usuarios</strong>
                Alta, edición y activación de cuentas.
            </a>

        <?php endif; ?>

        <a class="modulo" href="notificaciones.php">
            <strong>Notificaciones</strong>
            <?= $notificacionesNoLeidas ?> sin leer.
        </a>

        <div class="modulo deshabilitado">
            <strong>Reportes</strong>
            Próximamente.
        </div>

    </div>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
