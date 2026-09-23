<?php

/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
| Uso en una página con listado:
|
|   $paginacion = paginar($totalDeRegistros);
|   ... SQL con: LIMIT {$paginacion["por_pagina"]} OFFSET {$paginacion["offset"]}
|   <?= navegacionPaginas($paginacion) ?>
|
| Lee ?pagina= y ?por_pagina= de la URL y conserva los demás parámetros
| (filtros, búsqueda, pestañas) al cambiar de página.
*/

const OPCIONES_POR_PAGINA = [10, 20, 50, 100];

function paginar($total, $porPaginaDefecto = 20)
{
    $total = (int) $total;

    $porPagina = (int) ($_GET["por_pagina"] ?? $porPaginaDefecto);

    if (!in_array($porPagina, OPCIONES_POR_PAGINA, true)) {
        $porPagina = $porPaginaDefecto;
    }

    $totalPaginas = max(1, (int) ceil($total / $porPagina));

    $pagina = ctype_digit((string) ($_GET["pagina"] ?? "")) ? (int) $_GET["pagina"] : 1;

    // Una página fuera de rango (p. ej. tras borrar registros) se ajusta.
    $pagina = min(max(1, $pagina), $totalPaginas);

    $offset = ($pagina - 1) * $porPagina;

    return [
        "pagina" => $pagina,
        "por_pagina" => $porPagina,
        "total" => $total,
        "total_paginas" => $totalPaginas,
        "offset" => $offset,
        "desde" => $total > 0 ? $offset + 1 : 0,
        "hasta" => min($offset + $porPagina, $total)
    ];
}

/*
 * URL de la página actual con otros parámetros de paginación.
 */
function urlPagina(array $cambios)
{
    $parametros = array_merge($_GET, $cambios);

    if (($parametros["pagina"] ?? 1) == 1) {
        unset($parametros["pagina"]);
    }

    $consulta = http_build_query($parametros);

    return basename($_SERVER["PHP_SELF"]) . ($consulta !== "" ? "?" . $consulta : "");
}

/*
 * Números a mostrar: primera, última y dos alrededor de la actual,
 * con "…" en los huecos. Ej.: 1 … 4 5 [6] 7 8 … 20
 */
function numerosDePagina($actual, $total)
{
    $numeros = [];

    for ($i = 1; $i <= $total; $i++) {
        if ($i === 1 || $i === $total || abs($i - $actual) <= 2) {
            $numeros[] = $i;
        } elseif (end($numeros) !== "…") {
            $numeros[] = "…";
        }
    }

    return $numeros;
}

function navegacionPaginas(array $p, $etiqueta = "registros")
{
    if ($p["total"] === 0) {
        return "";
    }

    $html = '<div class="paginacion">';

    $html .= '<span class="texto-suave">Mostrando ' . $p["desde"] . '–' . $p["hasta"]
        . ' de ' . $p["total"] . ' ' . e($etiqueta) . '</span>';

    if ($p["total_paginas"] > 1) {

        $html .= '<nav class="paginas" aria-label="Paginación">';

        if ($p["pagina"] > 1) {
            $html .= '<a href="' . e(urlPagina(["pagina" => $p["pagina"] - 1])) . '" rel="prev">« Anterior</a>';
        } else {
            $html .= '<span class="deshabilitada">« Anterior</span>';
        }

        foreach (numerosDePagina($p["pagina"], $p["total_paginas"]) as $numero) {

            if ($numero === "…") {
                $html .= '<span class="hueco">…</span>';
            } elseif ($numero === $p["pagina"]) {
                $html .= '<span class="actual" aria-current="page">' . $numero . '</span>';
            } else {
                $html .= '<a href="' . e(urlPagina(["pagina" => $numero])) . '">' . $numero . '</a>';
            }
        }

        if ($p["pagina"] < $p["total_paginas"]) {
            $html .= '<a href="' . e(urlPagina(["pagina" => $p["pagina"] + 1])) . '" rel="next">Siguiente »</a>';
        } else {
            $html .= '<span class="deshabilitada">Siguiente »</span>';
        }

        $html .= '</nav>';
    }

    /*
     * Selector "por página": conserva los demás parámetros como campos ocultos.
     */
    $html .= '<form method="GET" class="por-pagina">';

    foreach ($_GET as $clave => $valor) {
        if (!in_array($clave, ["pagina", "por_pagina"], true) && is_scalar($valor)) {
            $html .= '<input type="hidden" name="' . e($clave) . '" value="' . e($valor) . '">';
        }
    }

    $html .= '<label for="por_pagina" class="texto-suave">Por página</label>';
    $html .= '<select id="por_pagina" name="por_pagina" data-autoenviar>';

    foreach (OPCIONES_POR_PAGINA as $opcion) {
        $html .= '<option value="' . $opcion . '"' . ($opcion === $p["por_pagina"] ? " selected" : "") . '>'
            . $opcion . '</option>';
    }

    $html .= '</select>';
    $html .= '<noscript><button type="submit" class="btn btn-secundario btn-sm">Aplicar</button></noscript>';
    $html .= '</form>';

    return $html . '</div>';
}
