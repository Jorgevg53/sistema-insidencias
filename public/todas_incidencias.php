<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/helpers/paginacion.php";
require_once "../app/models/Incidencia.php";

/*
 * Solo Administrador y Coordinador.
 */
requerirRol(["Administrador", "Coordinador"]);

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

    $estados = $conn->query("SELECT id, nombre FROM estados_incidencia ORDER BY id")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY id")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $prioridades = $conn->query("SELECT id, nombre FROM prioridades ORDER BY nivel")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $incidenciaModel = new Incidencia($conn);

    $responsables = $incidenciaModel->obtenerResponsables();

    $condiciones = [];
    $parametros = [];

    if (isset($estados[$filtro_estado])) {
        $condiciones[] = "i.estado_id = ?";
        $parametros[] = $filtro_estado;
    }

    if (isset($categorias[$filtro_categoria])) {
        $condiciones[] = "i.categoria_id = ?";
        $parametros[] = $filtro_categoria;
    }

    if (isset($prioridades[$filtro_prioridad])) {
        $condiciones[] = "i.prioridad_id = ?";
        $parametros[] = $filtro_prioridad;
    }

    if ($filtro_responsable === "sin") {
        $condiciones[] = "i.responsable_id IS NULL";
    } elseif (isset($responsables[$filtro_responsable])) {
        $condiciones[] = "i.responsable_id = ?";
        $parametros[] = $filtro_responsable;
    }

    if ($filtro_texto !== "") {
        $condiciones[] = "(i.folio LIKE ? OR i.titulo LIKE ?)";
        $parametros[] = "%" . $filtro_texto . "%";
        $parametros[] = "%" . $filtro_texto . "%";
    }

    $where = $condiciones ? "WHERE " . implode(" AND ", $condiciones) : "";

    /*
     * Total con los mismos filtros (para la paginación).
     */
    $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM incidencias i $where");
    $stmtTotal->execute($parametros);

    $paginacion = paginar($stmtTotal->fetchColumn());

    $sql = "
        SELECT
            i.id,
            i.folio,
            i.titulo,
            i.fecha_registro,
            CONCAT(u.nombre, ' ', COALESCE(u.apellido_paterno, '')) AS usuario,
            CONCAT(r.nombre, ' ', COALESCE(r.apellido_paterno, '')) AS responsable,
            c.nombre AS categoria,
            p.nombre AS prioridad,
            e.nombre AS estado
        FROM incidencias i
        INNER JOIN usuarios u
            ON i.usuario_id = u.id
        LEFT JOIN usuarios r
            ON i.responsable_id = r.id
        INNER JOIN categorias c
            ON i.categoria_id = c.id
        INNER JOIN prioridades p
            ON i.prioridad_id = p.id
        INNER JOIN estados_incidencia e
            ON i.estado_id = e.id
        $where
        ORDER BY i.fecha_registro DESC, i.id DESC
        LIMIT {$paginacion["por_pagina"]} OFFSET {$paginacion["offset"]}
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute($parametros);

    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

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
            <label for="categoria_id">Categoría</label>
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
