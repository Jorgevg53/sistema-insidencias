<?php

/*
|--------------------------------------------------------------------------
| VISTAS DE EVIDENCIAS
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../models/Evidencia.php";

/*
 * Campo para adjuntar archivos. El formulario debe tener
 * enctype="multipart/form-data".
 */
function campoEvidencias($id = "evidencias")
{
    return '
        <div class="zona-archivos">
            <label for="' . e($id) . '">Evidencias (opcional)</label>
            <div class="zona-archivos-caja">
                <span class="zona-archivos-icono">' . icono("clip") . '</span>
                <span>
                    <strong>Arrastra tus archivos aquí o haz clic para elegirlos</strong>
                    <small>
                        JPG, PNG, WEBP o PDF · máximo ' . Evidencia::MAX_ARCHIVOS . ' archivos de '
                        . Evidencia::formatearTamano(Evidencia::MAX_BYTES) . ' cada uno.
                    </small>
                    <small class="zona-archivos-elegidos" data-archivos-elegidos></small>
                </span>
                <input
                    type="file"
                    id="' . e($id) . '"
                    name="evidencias[]"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    multiple
                    data-max-archivos="' . Evidencia::MAX_ARCHIVOS . '"
                    data-max-bytes="' . Evidencia::MAX_BYTES . '"
                >
            </div>
        </div>
    ';
}

/*
 * Galería de evidencias. $puedeEliminar recibe cada evidencia y
 * devuelve true si el usuario puede borrarla.
 */
function listaEvidencias(array $evidencias, callable $puedeEliminar)
{
    if (!$evidencias) {
        return "";
    }

    $html = '<ul class="evidencias">';

    foreach ($evidencias as $evidencia) {

        $url = "evidencia.php?id=" . (int) $evidencia["id"];
        $nombre = e($evidencia["nombre_original"]);
        $tamano = e(Evidencia::formatearTamano($evidencia["tamano"]));

        $html .= '<li class="evidencia">';

        if (Evidencia::esImagen($evidencia)) {
            $html .= '<a href="' . $url . '" target="_blank" rel="noopener" class="evidencia-miniatura">'
                . '<img src="' . $url . '" alt="' . $nombre . '" loading="lazy">'
                . '</a>';
        } else {
            $html .= '<a href="' . $url . '" target="_blank" rel="noopener" class="evidencia-miniatura evidencia-pdf">'
                . icono("ticket") . '<span>PDF</span></a>';
        }

        $html .= '<div class="evidencia-datos">'
            . '<a href="' . $url . '" target="_blank" rel="noopener" title="' . $nombre . '">' . $nombre . '</a>'
            . '<span class="texto-suave">' . $tamano . ' · <a href="' . $url . '&amp;descargar=1">Descargar</a></span>';

        if ($puedeEliminar($evidencia)) {
            $html .= '<form method="POST" data-confirmar="¿Eliminar la evidencia «' . $nombre . '»?">'
                . campoCsrf()
                . '<input type="hidden" name="accion" value="eliminar_evidencia">'
                . '<input type="hidden" name="evidencia_id" value="' . (int) $evidencia["id"] . '">'
                . '<button type="submit" class="boton-enlace">Eliminar</button>'
                . '</form>';
        }

        $html .= '</div></li>';
    }

    return $html . '</ul>';
}
