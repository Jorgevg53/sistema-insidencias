<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Notificacion.php";

requerirSesion();

$database = new Database();
$conn = $database->conectar();

$notificacionModel = new Notificacion($conn);

$usuario_id = (int) $_SESSION["usuario_id"];

/*
|--------------------------------------------------------------------------
| ACCIONES
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    if (!verificarCsrf()) {

        flash("error", "La sesión del formulario expiró. Intenta de nuevo.");

    } elseif ($accion === "leer_todas") {

        $total = $notificacionModel->marcarTodasLeidas($usuario_id);

        flash("exito", $total . " notificación(es) marcadas como leídas.");

    } elseif ($accion === "leer" && ctype_digit((string) ($_POST["id"] ?? ""))) {

        $notificacionModel->marcarLeida($_POST["id"], $usuario_id);

    } elseif ($accion === "eliminar_leidas") {

        $total = $notificacionModel->eliminarLeidas($usuario_id);

        flash("exito", $total . " notificación(es) eliminadas.");
    }

    header("Location: notificaciones.php" . (($_GET["ver"] ?? "") === "no_leidas" ? "?ver=no_leidas" : ""));
    exit;
}

/*
|--------------------------------------------------------------------------
| LISTADO
|--------------------------------------------------------------------------
*/

$soloNoLeidas = ($_GET["ver"] ?? "") === "no_leidas";

$notificaciones = $notificacionModel->listar($usuario_id, $soloNoLeidas);

$noLeidas = $notificacionModel->contarNoLeidas($usuario_id);

$tituloPagina = "Notificaciones";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">

    <div>
        <h1>Notificaciones</h1>
        <span class="texto-suave"><?= $noLeidas ?> sin leer</span>
    </div>

    <div class="acciones">

        <?php if ($noLeidas > 0): ?>
            <form method="POST">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="leer_todas">
                <button type="submit" class="btn btn-secundario">Marcar todas como leídas</button>
            </form>
        <?php endif; ?>

        <form method="POST" data-confirmar="¿Eliminar todas las notificaciones leídas?">
            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="eliminar_leidas">
            <button type="submit" class="btn btn-peligro">Eliminar leídas</button>
        </form>

    </div>

</div>

<nav class="pestanas">
    <a href="notificaciones.php" class="<?= $soloNoLeidas ? "" : "activo" ?>">Todas</a>
    <a href="notificaciones.php?ver=no_leidas" class="<?= $soloNoLeidas ? "activo" : "" ?>">
        Sin leer (<?= $noLeidas ?>)
    </a>
</nav>

<section class="tarjeta">

    <?php if (empty($notificaciones)): ?>

        <p class="texto-suave">
            <?= $soloNoLeidas ? "No tienes notificaciones sin leer." : "No tienes notificaciones." ?>
        </p>

    <?php else: ?>

        <ul class="notificaciones">

            <?php foreach ($notificaciones as $notificacion): ?>

                <li class="notificacion <?= $notificacion["leida"] ? "" : "no-leida" ?>">

                    <div class="notificacion-cuerpo">

                        <?php if ($notificacion["incidencia_id"]): ?>
                            <a
                                class="notificacion-incidencia"
                                href="detalle_incidencia.php?id=<?= (int) $notificacion["incidencia_id"] ?>"
                            >
                                <?= e($notificacion["folio"]) ?> · <?= e($notificacion["titulo"]) ?>
                            </a>
                        <?php endif; ?>

                        <div><?= e($notificacion["mensaje"]) ?></div>

                        <span class="texto-suave notificacion-fecha"><?= e($notificacion["fecha"]) ?></span>

                    </div>

                    <?php if (!$notificacion["leida"]): ?>
                        <form method="POST">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="leer">
                            <input type="hidden" name="id" value="<?= (int) $notificacion["id"] ?>">
                            <button type="submit" class="btn btn-secundario btn-sm">Marcar leída</button>
                        </form>
                    <?php endif; ?>

                </li>

            <?php endforeach; ?>

        </ul>

        <p class="texto-suave">Se muestran las 100 más recientes. Al abrir una incidencia sus avisos se marcan como leídos.</p>

    <?php endif; ?>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
