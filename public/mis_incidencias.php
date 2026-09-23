<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";

requerirSesion();

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
            i.fecha_registro,
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
        WHERE i.usuario_id = ?
        ORDER BY i.fecha_registro DESC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([$_SESSION["usuario_id"]]);

    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = "No se pudieron consultar las incidencias.";

}

$tituloPagina = "Mis incidencias";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1>Mis incidencias</h1>
    <a href="incidencias.php" class="btn btn-primary">+ Registrar nueva incidencia</a>
</div>

<section class="tarjeta">

    <?php if ($error): ?>

        <div class="alerta alerta-error"><?= e($error) ?></div>

    <?php elseif (empty($incidencias)): ?>

        <p>No has registrado ninguna incidencia todavía.</p>

    <?php else: ?>

        <div class="tabla-contenedor">

            <table class="tabla">

                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($incidencias as $incidencia): ?>

                        <tr>
                            <td><?= e($incidencia["folio"]) ?></td>
                            <td><?= e($incidencia["titulo"]) ?></td>
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
                                    Ver detalle
                                </a>
                                ·
                                <a href="ticket.php?id=<?= (int) $incidencia["id"] ?>" target="_blank" rel="noopener">
                                    Ticket
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
