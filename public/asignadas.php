<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/helpers/paginacion.php";
require_once "../app/models/Incidencia.php";

/*
 * Incidencias donde el usuario en sesión es el responsable.
 */
requerirRol(Incidencia::ROLES_RESPONSABLES);

$incidencias = [];
$error = "";

/*
 * Por defecto se ocultan las terminadas; ?todas=1 las muestra.
 */
$verTodas = ($_GET["todas"] ?? "") === "1";

try {

    $database = new Database();
    $conn = $database->conectar();

    $filtroTerminadas = $verTodas ? "" : "AND e.nombre NOT IN ('Resuelta', 'Cerrada', 'Cancelada')";

    $stmtTotal = $conn->prepare("
        SELECT COUNT(*)
        FROM incidencias i
        INNER JOIN estados_incidencia e
            ON i.estado_id = e.id
        WHERE i.responsable_id = ?
        $filtroTerminadas
    ");
    $stmtTotal->execute([$_SESSION["usuario_id"]]);

    $paginacion = paginar($stmtTotal->fetchColumn());

    $sql = "
        SELECT
            i.id,
            i.folio,
            i.titulo,
            i.ubicacion,
            i.fecha_registro,
            i.fecha_actualizacion,
            c.nombre AS categoria,
            p.nombre AS prioridad,
            e.nombre AS estado
        FROM incidencias i
        INNER JOIN categorias c
            ON i.categoria_id = c.id
        INNER JOIN prioridades p
            ON i.prioridad_id = p.id
        INNER JOIN estados_incidencia e
            ON i.estado_id = e.id
        WHERE i.responsable_id = ?
        $filtroTerminadas
        ORDER BY p.nivel DESC, i.fecha_registro, i.id
        LIMIT {$paginacion["por_pagina"]} OFFSET {$paginacion["offset"]}
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([$_SESSION["usuario_id"]]);

    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = "No se pudieron consultar las incidencias.";

}

$tituloPagina = "Asignadas a mí";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">

    <h1>Incidencias asignadas a mí</h1>

    <?php if ($verTodas): ?>
        <a href="asignadas.php" class="btn btn-secundario">Ver solo pendientes de atender</a>
    <?php else: ?>
        <a href="asignadas.php?todas=1" class="btn btn-secundario">Incluir terminadas</a>
    <?php endif; ?>

</div>

<section class="tarjeta">

    <?php if ($error): ?>

        <div class="alerta alerta-error"><?= e($error) ?></div>

    <?php elseif (empty($incidencias)): ?>

        <p>No tienes incidencias <?= $verTodas ? "asignadas" : "pendientes de atender" ?>.</p>

    <?php else: ?>

        <p class="texto-suave">Ordenadas por prioridad y antigüedad.</p>

        <div class="tabla-contenedor">

            <table class="tabla">

                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Ubicación</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($incidencias as $incidencia): ?>

                        <tr>
                            <td><?= e($incidencia["folio"]) ?></td>
                            <td><?= e($incidencia["titulo"]) ?></td>
                            <td><?= e($incidencia["ubicacion"] ?: "—") ?></td>
                            <td><?= e($incidencia["categoria"]) ?></td>
                            <td>
                                <span class="badge <?= clasePrioridad($incidencia["prioridad"]) ?>">
                                    <?= e($incidencia["prioridad"]) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= claseEstado($incidencia["estado"]) ?>">
                                    <?= e($incidencia["estado"]) ?>
                                </span>
                            </td>
                            <td><?= e($incidencia["fecha_registro"]) ?></td>
                            <td>
                                <a href="detalle_incidencia.php?id=<?= (int) $incidencia["id"] ?>">
                                    Atender
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?= navegacionPaginas($paginacion, "incidencias") ?>

    <?php endif; ?>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
