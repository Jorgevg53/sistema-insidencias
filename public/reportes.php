<?php

require_once "../app/helpers/auth.php";
require_once "../app/config/database.php";
require_once "../app/models/Reporte.php";

/*
 * Solo Administrador y Coordinador.
 */
requerirRol(["Administrador", "Coordinador"]);

$database = new Database();
$conn = $database->conectar();

$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY id")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$prioridades = $conn->query("SELECT id, nombre FROM prioridades ORDER BY nivel")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$filtros = Reporte::filtrosDesdeRequest($_GET, $categorias, $prioridades);

$reporte = new Reporte($conn, $filtros);

$resumen = $reporte->resumen();
$porEstado = $reporte->porEstado();
$porCategoria = $reporte->porCategoria();
$porPrioridad = $reporte->porPrioridad();
$porMes = $reporte->porMes();
$porResponsable = $reporte->porResponsable();
$porUbicacion = $reporte->porUbicacion();
$porCarrera = $reporte->porCarrera();
$atrasadas = $reporte->abiertasMasAntiguas();

/*
 * Periodos rápidos.
 */
$hoy = new DateTime("today");

$periodos = [
    "Este mes" => [(clone $hoy)->modify("first day of this month")->format("Y-m-d"), $hoy->format("Y-m-d")],
    "Últimos 3 meses" => [(clone $hoy)->modify("-3 months")->format("Y-m-d"), $hoy->format("Y-m-d")],
    "Este año" => [$hoy->format("Y") . "-01-01", $hoy->format("Y-m-d")],
    "Todo" => ["", ""],
];

/*
 * Texto del periodo para el encabezado impreso.
 */
if ($filtros["desde"] && $filtros["hasta"]) {
    $textoPeriodo = "Del " . $filtros["desde"] . " al " . $filtros["hasta"];
} elseif ($filtros["desde"]) {
    $textoPeriodo = "Desde " . $filtros["desde"];
} elseif ($filtros["hasta"]) {
    $textoPeriodo = "Hasta " . $filtros["hasta"];
} else {
    $textoPeriodo = "Todo el historial";
}

if ($filtros["categoria_id"]) {
    $textoPeriodo .= " · Categoría: " . $categorias[$filtros["categoria_id"]];
}

if ($filtros["prioridad_id"]) {
    $textoPeriodo .= " · Prioridad: " . $prioridades[$filtros["prioridad_id"]];
}

$porcentajeAtendidas = $resumen["total"] > 0
    ? round($resumen["atendidas"] * 100 / $resumen["total"])
    : 0;

/*
 * Gráfica de barras horizontales hecha solo con HTML y CSS.
 */
function graficaBarras(array $filas, $campoEtiqueta, $campoValor, $claseEtiqueta = null)
{
    $maximo = max(array_merge([1], array_map("intval", array_column($filas, $campoValor))));

    $html = '<div class="barras">';

    foreach ($filas as $fila) {

        $valor = (int) $fila[$campoValor];
        $ancho = round($valor * 100 / $maximo);
        $etiqueta = $fila[$campoEtiqueta];

        $html .= '<div class="barra-fila">';
        $html .= $claseEtiqueta
            ? '<span class="barra-etiqueta"><span class="badge ' . $claseEtiqueta($etiqueta) . '">' . e($etiqueta) . '</span></span>'
            : '<span class="barra-etiqueta">' . e($etiqueta) . '</span>';
        $html .= '<div class="barra-pista"><div class="barra-relleno" style="width: ' . $ancho . '%"></div></div>';
        $html .= '<span class="barra-valor">' . $valor . '</span>';
        $html .= '</div>';
    }

    return $html . '</div>';
}

function nombreMes($mes)
{
    $meses = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];

    [$anio, $numero] = explode("-", $mes);

    return $meses[(int) $numero - 1] . " " . $anio;
}

$maximoMes = max(array_merge([1], array_map("intval", array_column($porMes, "registradas"))));

$tituloPagina = "Reportes";

require_once "../app/views/layouts/header.php";

?>

<div class="encabezado-pagina">

    <div>
        <h1>Reporte de incidencias</h1>
        <span class="texto-suave"><?= e($textoPeriodo) ?></span>
        <span class="solo-impresion texto-suave">
            · Generado el <?= date("Y-m-d H:i") ?> por <?= e($_SESSION["nombre"] . " " . ($_SESSION["apellido_paterno"] ?? "")) ?>
        </span>
    </div>

    <div class="acciones no-imprimir">
        <a href="reportes_exportar.php?<?= e(http_build_query($filtros)) ?>" class="btn btn-secundario">
            Exportar a Excel (CSV)
        </a>
        <button type="button" class="btn btn-primary" data-imprimir>
            Imprimir / Guardar PDF
        </button>
    </div>

</div>

<section class="tarjeta no-imprimir">

    <form method="GET" class="filtros">

        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= e($filtros["desde"]) ?>">
        </div>

        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= e($filtros["hasta"]) ?>">
        </div>

        <div>
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id">
                <option value="">Todas</option>
                <?php foreach ($categorias as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === (string) $filtros["categoria_id"] ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="prioridad_id">Prioridad</label>
            <select id="prioridad_id" name="prioridad_id">
                <option value="">Todas</option>
                <?php foreach ($prioridades as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= (string) $id === (string) $filtros["prioridad_id"] ? "selected" : "" ?>>
                        <?= e($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="acciones">
            <button type="submit" class="btn btn-primary">Generar</button>
        </div>

    </form>

    <div class="acciones" style="margin-top: 12px">
        <span class="texto-suave">Periodo rápido:</span>
        <?php foreach ($periodos as $texto => [$desde, $hasta]): ?>
            <a
                class="btn btn-secundario btn-sm"
                href="reportes.php?<?= e(http_build_query([
                    "desde" => $desde,
                    "hasta" => $hasta,
                    "categoria_id" => $filtros["categoria_id"],
                    "prioridad_id" => $filtros["prioridad_id"]
                ])) ?>"
            ><?= e($texto) ?></a>
        <?php endforeach; ?>
    </div>

</section>

<?php if ($resumen["total"] === 0): ?>

    <section class="tarjeta">
        <p>No hay incidencias registradas con estos filtros.</p>
    </section>

<?php else: ?>

    <div class="grid-resumen" style="margin-bottom: 20px">

        <div class="resumen-item">
            <span class="numero"><?= $resumen["total"] ?></span>
            <span class="etiqueta">Registradas</span>
        </div>

        <div class="resumen-item">
            <span class="numero"><?= $resumen["abiertas"] ?></span>
            <span class="etiqueta">Abiertas</span>
        </div>

        <div class="resumen-item">
            <span class="numero"><?= $resumen["atendidas"] ?></span>
            <span class="etiqueta">Atendidas (<?= $porcentajeAtendidas ?>%)</span>
        </div>

        <div class="resumen-item">
            <span class="numero"><?= $resumen["canceladas"] ?></span>
            <span class="etiqueta">Canceladas</span>
        </div>

        <div class="resumen-item <?= $resumen["sin_asignar"] > 0 ? "resumen-alerta" : "" ?>">
            <span class="numero"><?= $resumen["sin_asignar"] ?></span>
            <span class="etiqueta">Abiertas sin responsable</span>
        </div>

        <div class="resumen-item <?= $resumen["atrasadas"] > 0 ? "resumen-alerta" : "" ?>">
            <span class="numero"><?= $resumen["atrasadas"] ?></span>
            <span class="etiqueta">Abiertas con más de 7 días</span>
        </div>

        <div class="resumen-item">
            <span class="numero"><?= e(Reporte::formatearDuracion($resumen["horas_asignacion"])) ?></span>
            <span class="etiqueta">Tiempo promedio para asignar</span>
        </div>

        <div class="resumen-item">
            <span class="numero"><?= e(Reporte::formatearDuracion($resumen["horas_resolucion"])) ?></span>
            <span class="etiqueta">Tiempo promedio de resolución</span>
        </div>

    </div>

    <div class="grid-reportes">

        <section class="tarjeta">
            <h3>Por estado</h3>
            <?= graficaBarras($porEstado, "nombre", "total", "claseEstado") ?>
        </section>

        <section class="tarjeta">
            <h3>Por prioridad</h3>
            <?= graficaBarras($porPrioridad, "nombre", "total", "clasePrioridad") ?>
        </section>

        <section class="tarjeta">
            <h3>Por categoría</h3>
            <?= graficaBarras($porCategoria, "nombre", "total") ?>
        </section>

        <section class="tarjeta">
            <h3>Ubicaciones con más incidencias</h3>
            <?= graficaBarras($porUbicacion, "ubicacion", "total") ?>
        </section>

        <section class="tarjeta">
            <h3>Por carrera</h3>
            <?= graficaBarras($porCarrera, "carrera", "total") ?>
        </section>

    </div>

    <section class="tarjeta">

        <h3>Incidencias por mes</h3>

        <div class="leyenda">
            <span><i class="muestra muestra-registradas"></i> Registradas</span>
            <span><i class="muestra muestra-atendidas"></i> Ya atendidas</span>
        </div>

        <div class="columnas-mes">

            <?php foreach ($porMes as $mes): ?>

                <div class="columna-mes">

                    <div class="columna-barras">
                        <div
                            class="columna columna-registradas"
                            style="height: <?= round($mes["registradas"] * 100 / $maximoMes) ?>%"
                            title="Registradas: <?= (int) $mes["registradas"] ?>"
                        ><span><?= (int) $mes["registradas"] ?></span></div>
                        <div
                            class="columna columna-atendidas"
                            style="height: <?= round($mes["atendidas"] * 100 / $maximoMes) ?>%"
                            title="Atendidas: <?= (int) $mes["atendidas"] ?>"
                        ><span><?= (int) $mes["atendidas"] ?></span></div>
                    </div>

                    <div class="columna-etiqueta"><?= e(nombreMes($mes["mes"])) ?></div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

    <section class="tarjeta">

        <h3>Desempeño por responsable</h3>

        <?php if (empty($porResponsable)): ?>

            <p class="texto-suave">Ninguna incidencia de este periodo tiene responsable asignado.</p>

        <?php else: ?>

            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Responsable</th>
                            <th>Rol</th>
                            <th>Asignadas</th>
                            <th>Abiertas</th>
                            <th>Atendidas</th>
                            <th>Tiempo promedio de resolución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porResponsable as $fila): ?>
                            <tr>
                                <td><?= e($fila["responsable"]) ?></td>
                                <td><?= e($fila["rol"]) ?></td>
                                <td><?= (int) $fila["asignadas"] ?></td>
                                <td><?= (int) $fila["abiertas"] ?></td>
                                <td><?= (int) $fila["atendidas"] ?></td>
                                <td><?= e(Reporte::formatearDuracion($fila["horas_resolucion"])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

    <section class="tarjeta">

        <h3>Abiertas más antiguas</h3>

        <?php if (empty($atrasadas)): ?>

            <p class="texto-suave">No hay incidencias abiertas en este periodo.</p>

        <?php else: ?>

            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Título</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Responsable</th>
                            <th>Días abierta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atrasadas as $fila): ?>
                            <tr>
                                <td>
                                    <a href="detalle_incidencia.php?id=<?= (int) $fila["id"] ?>"><?= e($fila["folio"]) ?></a>
                                </td>
                                <td><?= e($fila["titulo"]) ?></td>
                                <td>
                                    <span class="badge <?= clasePrioridad($fila["prioridad"]) ?>"><?= e($fila["prioridad"]) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= claseEstado($fila["estado"]) ?>"><?= e($fila["estado"]) ?></span>
                                </td>
                                <td><?= e($fila["responsable"] ?: "Sin asignar") ?></td>
                                <td><?= (int) $fila["dias"] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

    <p class="texto-suave">
        "Atendidas" incluye las incidencias Resueltas y Cerradas. Los tiempos se calculan desde el
        registro hasta la asignación o el cierre.
    </p>

<?php endif; ?>

<?php require_once "../app/views/layouts/footer.php"; ?>
