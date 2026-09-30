<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/helpers/paginacion.php";
require_once "../app/models/Incidencia.php";

/*
 * Solo Administrador.
 */
requerirRol(["Administrador"]);

$incidencias = [];
$error = "";

/*
 * Filtros recibidos por GET (se conservan en la URL).
 */
$filtro_estado = $_GET["estado_id"] ?? "";
$filtro_categoria = $_GET["categoria_id"] ?? "";
$filtro_prioridad = $_GET["prioridad_id"] ?? "";
$filtro_responsable = $_GET["responsable_id"] ?? "";
$filtro_texto = trim($_GET["q"] ?? "");

try {

    $database = new Database();
    $conn = $database->conectar();

    $estados = Incidencia::ESTADOS;

    $categorias = [];
    foreach ($conn->getCollection("categorias")->find([], ["sort" => ["_id" => 1]]) as $c) {
        $categorias[(int) $c["_id"]] = $c["nombre"];
    }

    $prioridades = [];
    foreach ($conn->getCollection("prioridades")->find([], ["sort" => ["nivel" => 1]]) as $p) {
        $prioridades[(int) $p["_id"]] = $p["nombre"];
    }

    $incidenciaModel = new Incidencia($conn);

    $responsables = $incidenciaModel->obtenerResponsables();

    /*
     * Filtros ya validados contra los catálogos.
     */
    $filtros = [
        "estado_id" => isset($estados[$filtro_estado]) ? $filtro_estado : "",
        "categoria_id" => isset($categorias[$filtro_categoria]) ? $filtro_categoria : "",
        "prioridad_id" => isset($prioridades[$filtro_prioridad]) ? $filtro_prioridad : "",
        "responsable_id" => $filtro_responsable === "sin"
            ? "sin"
            : (isset($responsables[$filtro_responsable]) ? $filtro_responsable : ""),
        "q" => $filtro_texto,
    ];

    $paginacion = paginar($incidenciaModel->contarTodas($filtros));

    $incidencias = $incidenciaModel->listarTodas(
        $filtros,
        $paginacion["por_pagina"],
        $paginacion["offset"]
    );

} catch (Exception $e) {

    $error = "No se pudieron consultar las incidencias.";

}

$tituloPagina = "Todas las incidencias";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">
    <h1>Todas las incidencias</h1>
</div>

<section class="tarjeta">

    <form method="GET" class="filtros">

        <div>
            <label for="q">Buscar</label>
            <input
                type="search"
                id="q"
                name="q"
                value="<?= e($filtro_texto) ?>"
                placeholder="Folio o título"
            >
        </div>

        <div>
            <label for="estado_id">Estado</label>
            <select id="estado_id" name="estado_id">
                <option value="">Todos</option>
                <?php foreach ($estados ?? [] as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtro_estado ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="categoria_id">Tipo de incidencia</label>
            <select id="categoria_id" name="categoria_id">
                <option value="">Todas</option>
                <?php foreach ($categorias ?? [] as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtro_categoria ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="prioridad_id">Prioridad</label>
            <select id="prioridad_id" name="prioridad_id">
                <option value="">Todas</option>
                <?php foreach ($prioridades ?? [] as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtro_prioridad ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="responsable_id">Responsable</label>
            <select id="responsable_id" name="responsable_id">
                <option value="">Todos</option>
                <option value="sin" <?= $filtro_responsable === "sin" ? "selected" : "" ?>>Sin asignar</option>
                <?php foreach ($responsables ?? [] as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtro_responsable ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="todas_incidencias.php" class="btn btn-secundario">Limpiar</a>
        </div>

    </form>

</section>

<section class="tarjeta">

    <?php if ($error): ?>

        <div class="alerta alerta-error"><?= e($error) ?></div>

    <?php elseif (empty($incidencias)): ?>

        <p>No se encontraron incidencias.</p>

    <?php else: ?>


        <div class="tabla-contenedor">

            <table class="tabla">

                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Usuario</th>
                        <th>Responsable</th>
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
                            <td><?= e($incidencia["usuario"]) ?></td>
                            <td><?= e($incidencia["responsable"] ?: "Sin asignar") ?></td>
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
