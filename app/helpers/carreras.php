<?php

/*
|--------------------------------------------------------------------------
| CARRERAS
|--------------------------------------------------------------------------
| La lista oficial está en app/config/institucion.php.
*/

function carrerasOficiales()
{
    static $carreras = null;

    if ($carreras === null) {
        $carreras = (require __DIR__ . "/../config/institucion.php")["carreras"];
    }

    return $carreras;
}

/*
 * Una carrera es válida si está vacía ("No aplica"), es de la lista
 * oficial o es la que ya tenía guardada (datos anteriores a la lista).
 */
function carreraValida($carrera, $anterior = null)
{
    return $carrera === ""
        || in_array($carrera, carrerasOficiales(), true)
        || ($anterior !== null && $anterior !== "" && $carrera === $anterior);
}

/*
 * Lista desplegable de carreras. Si $valor no es de la lista oficial
 * (dato anterior), se conserva como opción para no perderlo.
 */
function campoCarrera($valor, $id = "carrera", $nombre = "carrera")
{
    $valor = (string) ($valor ?? "");

    $opciones = carrerasOficiales();

    $html = '<select id="' . e($id) . '" name="' . e($nombre) . '">';
    $html .= '<option value="">— No aplica —</option>';

    if ($valor !== "" && !in_array($valor, $opciones, true)) {
        $html .= '<option value="' . e($valor) . '" selected>' . e($valor) . ' (anterior)</option>';
    }

    foreach ($opciones as $carrera) {
        $html .= '<option value="' . e($carrera) . '"' . ($carrera === $valor ? " selected" : "") . '>'
            . e($carrera) . '</option>';
    }

    return $html . '</select>';
}
