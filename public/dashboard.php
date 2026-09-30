<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Incidencia.php";

requerirSesion();

$estados = [];
$total = 0;
$error = "";

try {

    $database = new Database();
    $conn = $database->conectar();

    /*
     * El Administrador ve el resumen de todas
     * las incidencias; los demás roles solo las propias.
     */
    $conteo = (new Incidencia($conn))->conteoPorEstado(
        esGestor() ? null : (int) $_SESSION["usuario_id"]
    );

    foreach ($conteo as $nombre => $cantidad) {
        $estados[] = ["estado" => $nombre, "total" => $cantidad];
        $total += $cantidad;
    }

} catch (Exception $e) {

    $error = "No se pudo obtener el resumen de incidencias.";

}

$tituloPagina = "Inicio";

require_once "../app/views/layouts/header.php";

?>

<section class="bienvenida">

    <div>
        <span class="bienvenida-fecha"><?= icono("calendario") ?> <?= e(ucfirst(fechaLarga())) ?></span>
        <h1>¡Hola, <?= e($_SESSION["nombre"]) ?>!</h1>
        <p><?= e($_SESSION["correo"]) ?> · <?= e($_SESSION["rol"]) ?></p>
    </div>

    <div class="acciones">
        <a href="incidencias.php" class="btn btn-claro btn-lg">
            <?= icono("mas") ?> Registrar incidencia
        </a>
        <a href="<?= esGestor() ? "todas_incidencias.php" : "mis_incidencias.php" ?>" class="btn btn-transparente btn-lg">
            <?= icono("lista") ?> <?= esGestor() ? "Ver todas" : "Mis incidencias" ?>
        </a>
    </div>

</section>

<section class="tarjeta">

    <div class="tarjeta-cabecera">
        <h3>
            <?= icono("grafica") ?>
            <?= esGestor() ? "Resumen general de incidencias" : "Resumen de mis incidencias" ?>
        </h3>
        <?php if (!$error && $total > 0): ?>
            <span class="texto-suave"><?= $total ?> en total</span>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>

        <div class="alerta alerta-error"><?= e($error) ?></div>

    <?php else: ?>

        <?php if ($total > 0): ?>
            <div class="distribucion" role="img" aria-label="Distribución de incidencias por estado">
                <?php foreach ($estados as $estado): ?>
                    <?php if ($estado["total"] > 0): ?>
                        <span
                            class="<?= claseTono(claseEstado($estado["estado"])) ?>"
                            style="flex-grow: <?= (int) $estado["total"] ?>"
                            title="<?= e($estado["estado"]) ?>: <?= (int) $estado["total"] ?>"
                        ></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid-resumen">

            <div class="resumen-item tono-neutro">
                <span class="resumen-icono"><?= icono("carpeta") ?></span>
                <span class="numero"><?= $total ?></span>
                <span class="etiqueta">Total de incidencias</span>
                <div class="resumen-barra"><span style="width: 100%"></span></div>
            </div>

            <?php foreach ($estados as $estado): ?>

                <div class="resumen-item <?= claseTono(claseEstado($estado["estado"])) ?>">
                    <span class="resumen-icono"><?= icono(iconoEstado($estado["estado"])) ?></span>
                    <span class="numero"><?= (int) $estado["total"] ?></span>
                    <span class="etiqueta"><?= e($estado["estado"]) ?></span>
                    <div class="resumen-barra">
                        <span style="width: <?= $total > 0 ? round($estado["total"] * 100 / $total) : 0 ?>%"></span>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php

/*
 * Accesos directos según el rol: [archivo, título, descripción, ícono, visible].
 */
$modulos = [
    ["incidencias.php", "Registrar incidencia", "Reporta un problema con el sistema de calificaciones.", "mas", true],
    ["mis_incidencias.php", "Mis incidencias", "Consulta el estado de tus reportes.", "lista", true],
    ["asignadas.php", "Asignadas a mí", "Atiende las incidencias que te asignaron.", "bandeja",
        esGestor()],
    ["todas_incidencias.php", "Todas las incidencias", "Revisa, filtra y cambia el estado de los reportes.", "capas", esGestor()],
    ["reportes.php", "Reportes", "Estadísticas, tiempos de atención y exportación.", "grafica", esGestor()],
    ["usuarios.php", "Usuarios", "Alta, edición y activación de cuentas.", "usuarios", tieneRol("Administrador")],
    ["catalogos.php", "Catálogos", "Tipos de incidencia del sistema de calificaciones y prioridades.", "ajustes", tieneRol("Administrador")],
    ["notificaciones.php", "Notificaciones",
        $notificacionesNoLeidas === 1 ? "1 aviso sin leer." : $notificacionesNoLeidas . " avisos sin leer.", "campana", true],
];

?>

<section class="tarjeta">

    <h3><?= icono("inicio") ?> Accesos rápidos</h3>

    <div class="grid-modulos">

        <?php foreach ($modulos as [$archivo, $titulo, $descripcion, $icono, $visible]): ?>

            <?php if ($visible): ?>

                <a class="modulo" href="<?= e($archivo) ?>">
                    <span class="modulo-icono"><?= icono($icono) ?></span>
                    <span>
                        <strong><?= e($titulo) ?></strong>
                        <?= e($descripcion) ?>
                    </span>
                    <?= icono("flecha_der", "icono modulo-flecha") ?>
                </a>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>

</section>

<?php require_once "../app/views/layouts/footer.php"; ?>
