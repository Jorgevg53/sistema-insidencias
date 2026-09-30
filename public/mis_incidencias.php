<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/helpers/paginacion.php";
require_once "../app/models/Incidencia.php";

requerirSesion();

$incidencias = [];
$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    $incidenciaModel = new Incidencia($conn);

    $paginacion = paginar($incidenciaModel->contarDeUsuario($_SESSION["usuario_id"]));

    $incidencias = $incidenciaModel->listarDeUsuario(
        $_SESSION["usuario_id"],
        $paginacion["por_pagina"],
        $paginacion["offset"]
    );

} catch (Exception $e) {

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
                        <th>Tipo de incidencia</th>
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

        <?= navegacionPaginas($paginacion, "incidencias") ?>

    <?php endif; ?>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
