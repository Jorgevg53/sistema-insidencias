<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";
require_once "../app/models/Notificacion.php";
require_once "../app/helpers/evidencias.php";

requerirSesion();

$id = $_GET["id"] ?? "";

if (!ctype_digit((string) $id)) {
    die("Incidencia no válida.");
}

$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    $incidenciaModel = new Incidencia($conn);
    $evidenciaModel = new Evidencia($conn);

    $incidencia = $incidenciaModel->buscarPorId($id);

} catch (PDOException $e) {

    die("Error en la base de datos: " . $e->getMessage());

}

/*
|--------------------------------------------------------------------------
| PERMISOS
|--------------------------------------------------------------------------
| - Gestor (Administrador/Coordinador): ve todo, cambia estado y asigna.
| - Responsable asignado: ve la incidencia y la atiende (En proceso/Resuelta).
| - Usuario que la reportó: la ve, comenta y puede cancelarla si sigue Pendiente.
*/

$usuario_id = (int) $_SESSION["usuario_id"];

$esDueno = $incidencia && (int) $incidencia["usuario_id"] === $usuario_id;
$esResponsable = $incidencia && (int) $incidencia["responsable_id"] === $usuario_id;

if (!$incidencia || (!esGestor() && !$esDueno && !$esResponsable)) {
    flash("error", "La incidencia no existe o no tienes permiso para verla.");
    header("Location: " . (esGestor() ? "todas_incidencias.php" : "mis_incidencias.php"));
    exit;
}

$estados = $incidenciaModel->obtenerEstados();
$responsables = esGestor() ? $incidenciaModel->obtenerResponsables() : [];

/*
 * Si el responsable actual ya no está disponible (p. ej. fue desactivado)
 * se conserva en la lista para no quitarlo sin querer al guardar.
 */
if (
    esGestor() &&
    $incidencia["responsable_id"] !== null &&
    !isset($responsables[$incidencia["responsable_id"]])
) {
    $responsables[$incidencia["responsable_id"]] = trim($incidencia["responsable"]) . " (no disponible)";
}

/*
 * Opciones para reclasificar (solo gestores).
 */
$categoriasGestion = esGestor() ? $incidenciaModel->opcionesClasificacion("categorias", $incidencia["categoria_id"]) : [];
$prioridadesGestion = esGestor() ? $incidenciaModel->opcionesClasificacion("prioridades", $incidencia["prioridad_id"]) : [];

$bloqueada = in_array($incidencia["estado"], Incidencia::ESTADOS_BLOQUEADOS, true);

$puedeAtender = !esGestor() && $esResponsable && !$bloqueada;
$puedeComentar = !$bloqueada;
$puedeCancelar = $esDueno && $incidencia["estado"] === "Pendiente";

/*
 * Una evidencia la puede eliminar un gestor, o quien la subió
 * mientras la incidencia siga abierta a comentarios.
 */
$puedeEliminarEvidencia = function ($evidencia) use ($usuario_id, $bloqueada) {
    return esGestor() || (!$bloqueada && (int) $evidencia["usuario_id"] === $usuario_id);
};

/*
 * Estados que el responsable puede elegir (más el actual).
 */
$estadosResponsable = array_filter(
    $estados,
    function ($nombre, $estadoId) use ($incidencia) {
        return in_array($nombre, Incidencia::ESTADOS_RESPONSABLE, true)
            || (int) $estadoId === (int) $incidencia["estado_id"];
    },
    ARRAY_FILTER_USE_BOTH
);

/*
|--------------------------------------------------------------------------
| ACCIONES (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $comentario = trim($_POST["comentario"] ?? "");
    $mensaje = "";

    /*
     * Evidencias: solo en los formularios que llevan comentario.
     */
    [$archivos, $errorArchivos] = in_array($accion, ["gestionar", "atender", "comentar"], true)
        ? Evidencia::validarSubida()
        : [[], null];

    $archivoAEliminar = null;

    /*
     * Guarda el comentario (si hay texto o archivos) con sus evidencias.
     * Devuelve true si se guardó algo.
     */
    $guardarComentario = function () use (&$incidencia, $incidenciaModel, $evidenciaModel, $usuario_id, $comentario, $archivos) {

        if ($comentario === "" && !$archivos) {
            return false;
        }

        $comentario_id = $incidenciaModel->agregarComentario($incidencia, $usuario_id, $comentario, count($archivos));

        $evidenciaModel->guardar($incidencia["id"], $comentario_id, $usuario_id, $archivos);

        return true;
    };

    if (peticionDemasiadoGrande()) {

        $error = mensajePeticionDemasiadoGrande();

    } elseif (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif (mb_strlen($comentario) > 2000) {

        $error = "El comentario admite máximo 2000 caracteres.";

    } elseif ($errorArchivos) {

        $error = $errorArchivos;

    } else {

        try {

            $conn->beginTransaction();

            switch ($accion) {

                /*
                 * Gestor: cambiar estado y/o responsable.
                 */
                case "gestionar":

                    if (!esGestor()) {
                        $error = "No tienes permiso para realizar esta acción.";
                        break;
                    }

                    $estado_id = $_POST["estado_id"] ?? "";
                    $responsable_id = $_POST["responsable_id"] ?? "";
                    $categoria_id = $_POST["categoria_id"] ?? $incidencia["categoria_id"];
                    $prioridad_id = $_POST["prioridad_id"] ?? $incidencia["prioridad_id"];

                    if (!isset($estados[$estado_id])) {
                        $error = "El estado seleccionado no es válido.";
                        break;
                    }

                    if (!isset($categoriasGestion[$categoria_id]) || !isset($prioridadesGestion[$prioridad_id])) {
                        $error = "La categoría o la prioridad seleccionada no es válida.";
                        break;
                    }

                    if ($responsable_id !== "" && !isset($responsables[$responsable_id])) {
                        $error = "El responsable seleccionado no es válido.";
                        break;
                    }

                    $responsable_id = $responsable_id === "" ? null : (int) $responsable_id;

                    $reclasificada = $incidenciaModel->reclasificar(
                        $incidencia,
                        (int) $categoria_id,
                        (int) $prioridad_id,
                        $usuario_id,
                        $categoriasGestion,
                        $prioridadesGestion
                    );

                    $asignado = $incidenciaModel->asignarResponsable($incidencia, $responsable_id, $usuario_id);

                    /*
                     * Si se asigna responsable y el gestor no cambió el estado,
                     * una incidencia Pendiente o En revisión pasa a "Asignada".
                     */
                    if (
                        $asignado &&
                        $responsable_id !== null &&
                        (int) $estado_id === (int) $incidencia["estado_id"] &&
                        in_array($incidencia["estado"], ["Pendiente", "En revisión"], true)
                    ) {
                        $estado_id = array_search("Asignada", $estados, true);
                    }

                    $cambioEstado = $incidenciaModel->cambiarEstado($incidencia, $estado_id, $usuario_id);

                    $comentado = $guardarComentario();

                    $mensaje = ($reclasificada || $asignado || $cambioEstado || $comentado)
                        ? "La incidencia se actualizó correctamente."
                        : "No hubo cambios que guardar.";

                    break;

                /*
                 * Responsable asignado: avanzar la atención.
                 */
                case "atender":

                    if (!$puedeAtender) {
                        $error = "No tienes permiso para realizar esta acción.";
                        break;
                    }

                    $estado_id = $_POST["estado_id"] ?? "";

                    if (!isset($estadosResponsable[$estado_id])) {
                        $error = "El estado seleccionado no es válido.";
                        break;
                    }

                    if ($estados[$estado_id] === "Resuelta" && $comentario === "") {
                        $error = "Describe brevemente la solución para marcarla como Resuelta.";
                        break;
                    }

                    $cambioEstado = $incidenciaModel->cambiarEstado($incidencia, $estado_id, $usuario_id);

                    $comentado = $guardarComentario();

                    $mensaje = ($cambioEstado || $comentado)
                        ? "La incidencia se actualizó correctamente."
                        : "No hubo cambios que guardar.";

                    break;

                /*
                 * Cualquier participante: agregar comentario.
                 */
                case "comentar":

                    if (!$puedeComentar) {
                        $error = "La incidencia ya no admite comentarios.";
                        break;
                    }

                    if ($comentario === "" && !$archivos) {
                        $error = "Escribe un comentario o adjunta una evidencia.";
                        break;
                    }

                    $guardarComentario();

                    $mensaje = $archivos ? "Comentario y evidencias agregados." : "Comentario agregado.";

                    break;

                /*
                 * Eliminar una evidencia (gestor o quien la subió).
                 */
                case "eliminar_evidencia":

                    $evidencia = ctype_digit((string) ($_POST["evidencia_id"] ?? ""))
                        ? $evidenciaModel->buscarPorId($_POST["evidencia_id"])
                        : false;

                    if (!$evidencia || (int) $evidencia["incidencia_id"] !== (int) $incidencia["id"]) {
                        $error = "La evidencia no existe.";
                        break;
                    }

                    if (!$puedeEliminarEvidencia($evidencia)) {
                        $error = "No tienes permiso para eliminar esta evidencia.";
                        break;
                    }

                    $evidenciaModel->eliminar($evidencia);

                    $incidenciaModel->registrarHistorial(
                        $incidencia["id"],
                        $usuario_id,
                        "evidencia",
                        mb_substr("Se eliminó la evidencia «" . $evidencia["nombre_original"] . "»", 0, 255)
                    );

                    $archivoAEliminar = $evidencia["archivo"];

                    $mensaje = "Evidencia eliminada.";

                    break;

                /*
                 * Usuario que reportó: cancelar mientras siga Pendiente.
                 */
                case "cancelar":

                    if (!$puedeCancelar) {
                        $error = "Solo puedes cancelar incidencias pendientes que tú registraste.";
                        break;
                    }

                    $incidenciaModel->cambiarEstado(
                        $incidencia,
                        array_search("Cancelada", $estados, true),
                        $usuario_id
                    );

                    if ($comentario !== "") {
                        $incidenciaModel->agregarComentario($incidencia, $usuario_id, $comentario);
                    }

                    $mensaje = "La incidencia fue cancelada.";

                    break;

                default:

                    $error = "Acción no válida.";
            }

            if ($error) {

                $conn->rollBack();

                $evidenciaModel->deshacer();

                $incidencia = $incidenciaModel->buscarPorId($id);

            } else {

                /*
                 * Una notificación por persona con todos los cambios.
                 */
                $incidenciaModel->enviarAvisos($incidencia["id"], $usuario_id);

                $conn->commit();

                /*
                 * El archivo se borra solo cuando el cambio ya quedó guardado.
                 */
                if ($archivoAEliminar) {
                    Evidencia::borrarArchivo($archivoAEliminar);
                }

                flash("exito", $mensaje);

                header("Location: detalle_incidencia.php?id=" . $incidencia["id"]);
                exit;
            }

        } catch (PDOException | RuntimeException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $evidenciaModel->deshacer();

            $incidencia = $incidenciaModel->buscarPorId($id);

            $error = $e instanceof RuntimeException ? $e->getMessage() : "No se pudo guardar el cambio.";
        }
    }
}

$seguimiento = $incidenciaModel->obtenerSeguimiento($incidencia["id"]);

$evidencias = $evidenciaModel->listarPorIncidencia($incidencia["id"]);

/*
 * Al ver la incidencia se dan por leídas sus notificaciones.
 */
$notificacionModel = new Notificacion($conn);

$notificacionModel->marcarLeidasDeIncidencia($incidencia["id"], $usuario_id);

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

    <?php if ($evidencias["inicial"]): ?>
        <h3>Evidencias</h3>
        <?= listaEvidencias($evidencias["inicial"], $puedeEliminarEvidencia) ?>
    <?php endif; ?>

</section>

<?php if (esGestor()): ?>

    <section class="tarjeta">

        <h3>Gestionar incidencia</h3>

        <form method="POST" class="formulario" enctype="multipart/form-data">

            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="gestionar">

            <div class="formulario-2col">

                <div>
                    <label for="estado_id">Estado</label>
                    <select name="estado_id" id="estado_id" required>
                        <?php foreach ($estados as $estadoId => $nombre): ?>
                            <option
                                value="<?= $estadoId ?>"
                                <?= (int) $estadoId === (int) $incidencia["estado_id"] ? "selected" : "" ?>
                            >
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="categoria_gestion">Categoría</label>
                    <select name="categoria_id" id="categoria_gestion" required>
                        <?php foreach ($categoriasGestion as $categoriaId => $nombre): ?>
                            <option
                                value="<?= $categoriaId ?>"
                                <?= (int) $categoriaId === (int) $incidencia["categoria_id"] ? "selected" : "" ?>
                            >
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="prioridad_gestion">Prioridad</label>
                    <select name="prioridad_id" id="prioridad_gestion" required>
                        <?php foreach ($prioridadesGestion as $prioridadId => $nombre): ?>
                            <option
                                value="<?= $prioridadId ?>"
                                <?= (int) $prioridadId === (int) $incidencia["prioridad_id"] ? "selected" : "" ?>
                            >
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="responsable_id">Responsable</label>
                    <select name="responsable_id" id="responsable_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($responsables as $responsableId => $nombre): ?>
                            <option
                                value="<?= $responsableId ?>"
                                <?= (int) $responsableId === (int) $incidencia["responsable_id"] ? "selected" : "" ?>
                            >
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div>
                <label for="comentario_gestion">Comentario (opcional)</label>
                <textarea
                    id="comentario_gestion"
                    name="comentario"
                    rows="3"
                    maxlength="2000"
                    placeholder="Ej. Se asigna al área de soporte técnico."
                ></textarea>
            </div>

            <?= campoEvidencias("evidencias_gestion") ?>

            <p class="texto-suave" style="margin: 0">
                Al asignar un responsable, una incidencia Pendiente o En revisión pasa a "Asignada".
                Si corriges la categoría o la prioridad, el cambio queda en el seguimiento.
            </p>

            <div class="acciones">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>

        </form>

    </section>

<?php elseif ($puedeAtender): ?>

    <section class="tarjeta">

        <h3>Atender incidencia</h3>

        <p class="texto-suave">Esta incidencia está asignada a ti.</p>

        <form method="POST" class="formulario" enctype="multipart/form-data">

            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="atender">

            <div>
                <label for="estado_atender">Estado</label>
                <select name="estado_id" id="estado_atender" required style="max-width: 260px">
                    <?php foreach ($estadosResponsable as $estadoId => $nombre): ?>
                        <option
                            value="<?= $estadoId ?>"
                            <?= (int) $estadoId === (int) $incidencia["estado_id"] ? "selected" : "" ?>
                        >
                            <?= e($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="comentario_atender">Comentario</label>
                <textarea
                    id="comentario_atender"
                    name="comentario"
                    rows="3"
                    maxlength="2000"
                    placeholder="Describe el avance o la solución aplicada."
                ></textarea>
            </div>

            <?= campoEvidencias("evidencias_atender") ?>

            <div class="acciones">
                <button type="submit" class="btn btn-primary">Guardar avance</button>
            </div>

        </form>

    </section>

<?php endif; ?>

<section class="tarjeta">

    <h3>Seguimiento</h3>

    <?php if (empty($seguimiento)): ?>

        <p class="texto-suave">Aún no hay movimientos.</p>

    <?php else: ?>

        <ol class="timeline">

            <?php foreach ($seguimiento as $evento): ?>

                <li class="evento evento-<?= e($evento["tipo"] === "comentario" ? "comentario" : $evento["accion"]) ?>">

                    <div class="evento-cabecera">
                        <strong><?= e($evento["usuario"]) ?></strong>
                        <span class="texto-suave">· <?= e($evento["rol"]) ?> · <?= e($evento["fecha"]) ?></span>
                    </div>

                    <?php if ($evento["tipo"] === "comentario"): ?>
                        <?php if ($evento["texto"] !== ""): ?>
                            <div class="evento-comentario-texto"><?= nl2br(e($evento["texto"])) ?></div>
                        <?php endif; ?>
                        <?= listaEvidencias($evidencias["comentarios"][$evento["id"]] ?? [], $puedeEliminarEvidencia) ?>
                    <?php else: ?>
                        <div><?= e($evento["texto"]) ?></div>
                    <?php endif; ?>

                </li>

            <?php endforeach; ?>

        </ol>

    <?php endif; ?>

    <?php if ($puedeComentar): ?>

        <form method="POST" class="formulario" style="margin-top: 16px" enctype="multipart/form-data">

            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="comentar">

            <div>
                <label for="comentario">Agregar comentario</label>
                <textarea id="comentario" name="comentario" rows="3" maxlength="2000"></textarea>
            </div>

            <?= campoEvidencias("evidencias_comentario") ?>

            <div class="acciones">
                <button type="submit" class="btn btn-primary">Comentar</button>
            </div>

        </form>

    <?php else: ?>

        <p class="texto-suave">La incidencia está <?= e(mb_strtolower($incidencia["estado"])) ?> y ya no admite comentarios.</p>

    <?php endif; ?>

</section>

<?php if ($puedeCancelar): ?>

    <section class="tarjeta">

        <h3>Cancelar incidencia</h3>

        <p class="texto-suave">Puedes cancelarla mientras siga Pendiente (por ejemplo, si el problema ya se resolvió solo).</p>

        <form
            method="POST"
            class="formulario"
            data-confirmar="¿Seguro que deseas cancelar esta incidencia?"
        >

            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="cancelar">

            <div>
                <label for="motivo">Motivo (opcional)</label>
                <textarea id="motivo" name="comentario" rows="2" maxlength="2000"></textarea>
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-peligro">Cancelar incidencia</button>
            </div>

        </form>

    </section>

<?php endif; ?>

<a href="<?= esGestor() ? "todas_incidencias.php" : ($esDueno ? "mis_incidencias.php" : "asignadas.php") ?>">
    ← Regresar
</a>

<?php require_once "../app/views/layouts/footer.php"; ?>
