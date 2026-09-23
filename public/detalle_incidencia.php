<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";

requerirSesion();

$id = $_GET["id"] ?? "";

if (!ctype_digit((string) $id)) {
    die("Incidencia no válida.");
}

/*
 * Estados que dan por terminada la incidencia:
 * al llegar a ellos se guarda la fecha de cierre.
 */
const ESTADOS_FINALES = ["Resuelta", "Cerrada", "Cancelada"];

$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    $estados = $conn->query("SELECT id, nombre FROM estados_incidencia ORDER BY id")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO (solo Administrador y Coordinador)
    |--------------------------------------------------------------------------
    */

    if ($_SERVER["REQUEST_METHOD"] === "POST" && esGestor()) {

        $estado_id = $_POST["estado_id"] ?? "";

        if (!verificarCsrf()) {

            $error = "La sesión del formulario expiró. Intenta de nuevo.";

        } elseif (!isset($estados[$estado_id])) {

            $error = "El estado seleccionado no es válido.";

        } else {

            $esFinal = in_array($estados[$estado_id], ESTADOS_FINALES, true);

            /*
             * Si el nuevo estado es final se guarda la fecha de cierre
             * (solo la primera vez); si se reabre, se limpia.
             */
            $sqlUpdate = "
                UPDATE incidencias
                SET
                    estado_id = ?,
                    fecha_cierre = " . ($esFinal ? "COALESCE(fecha_cierre, NOW())" : "NULL") . "
                WHERE id = ?
            ";

            $stmtUpdate = $conn->prepare($sqlUpdate);

            $stmtUpdate->execute([$estado_id, $id]);

            flash("exito", "El estado de la incidencia se actualizó correctamente.");

            header("Location: detalle_incidencia.php?id=" . $id);
            exit;
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
            i.usuario_id,
            i.estado_id,
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

        WHERE i.id = ?

        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);

    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Error en la base de datos: " . $e->getMessage());

}

/*
 * Un usuario sin rol de gestor solo puede ver sus propias incidencias.
 */
if (
    !$incidencia ||
    (!esGestor() && (int) $incidencia["usuario_id"] !== (int) $_SESSION["usuario_id"])
) {
    flash("error", "La incidencia no existe o no tienes permiso para verla.");
    header("Location: " . (esGestor() ? "todas_incidencias.php" : "mis_incidencias.php"));
    exit;
}

$tituloPagina = "Incidencia " . $incidencia["folio"];

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">

    <div>
        <span class="texto-suave"><?= e($incidencia["folio"]) ?></span>
        <h1><?= e($incidencia["titulo"]) ?></h1>
    </div>

    <span class="badge <?= claseEstado($incidencia["estado"]) ?>">
        <?= e($incidencia["estado"]) ?>
    </span>

</div>

<?php if ($error): ?>
    <div class="alerta alerta-error"><?= e($error) ?></div>
<?php endif; ?>

<section class="tarjeta">

    <dl class="detalle-grid">

        <div>
            <dt>Usuario que reportó</dt>
            <dd>
                <?= e(trim(
                    $incidencia["nombre"] . " " .
                    $incidencia["apellido_paterno"] . " " .
                    $incidencia["apellido_materno"]
                )) ?>
            </dd>
        </div>

        <div>
            <dt>Correo</dt>
            <dd><?= e($incidencia["correo"]) ?></dd>
        </div>

        <div>
            <dt>Categoría</dt>
            <dd><?= e($incidencia["categoria"]) ?></dd>
        </div>

        <div>
            <dt>Prioridad</dt>
            <dd>
                <span class="badge <?= clasePrioridad($incidencia["prioridad"]) ?>">
                    <?= e($incidencia["prioridad"]) ?>
                </span>
            </dd>
        </div>

        <div>
            <dt>Ubicación</dt>
            <dd><?= e($incidencia["ubicacion"] ?: "No especificada") ?></dd>
        </div>

        <div>
            <dt>Responsable</dt>
            <dd><?= e($incidencia["responsable"] ?: "Sin asignar") ?></dd>
        </div>

        <div>
            <dt>Fecha de registro</dt>
            <dd><?= e($incidencia["fecha_registro"]) ?></dd>
        </div>

        <div>
            <dt>Última actualización</dt>
            <dd><?= e($incidencia["fecha_actualizacion"]) ?></dd>
        </div>

        <?php if (!empty($incidencia["fecha_cierre"])): ?>
            <div>
                <dt>Fecha de cierre</dt>
                <dd><?= e($incidencia["fecha_cierre"]) ?></dd>
            </div>
        <?php endif; ?>

    </dl>

    <h3>Descripción</h3>

    <p><?= nl2br(e($incidencia["descripcion"])) ?></p>

</section>

<?php if (esGestor()): ?>

    <section class="tarjeta">

        <h3>Gestionar estado</h3>

        <form method="POST" class="acciones">

            <?= campoCsrf() ?>

            <select name="estado_id" id="estado_id" required style="max-width: 260px">
                <?php foreach ($estados as $estadoId => $nombre): ?>
                    <option
                        value="<?= $estadoId ?>"
                        <?= (int) $estadoId === (int) $incidencia["estado_id"] ? "selected" : "" ?>
                    >
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Guardar cambios</button>

        </form>

    </section>

<?php endif; ?>

<a href="<?= esGestor() ? "todas_incidencias.php" : "mis_incidencias.php" ?>">
    ← Regresar
</a>

<?php require_once "../app/views/layouts/footer.php"; ?>
