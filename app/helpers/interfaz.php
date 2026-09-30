<?php

/*
|--------------------------------------------------------------------------
| AYUDANTES DE INTERFAZ
|--------------------------------------------------------------------------
| Íconos SVG en línea (trazos estilo "Lucide", 24x24) e iniciales para
| los avatares. No dependen de archivos externos.
*/

const ICONOS = [
    "inicio" => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    "mas" => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    "lista" => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h3"/>',
    "bandeja" => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    "capas" => '<path d="m12 2 10 5-10 5L2 7l10-5z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
    "grafica" => '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
    "usuarios" => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    "ajustes" => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    "usuario" => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    "campana" => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    "salir" => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    "menu" => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    "cerrar" => '<path d="M18 6 6 18M6 6l12 12"/>',
    "buscar" => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    "filtro" => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
    "flecha_izq" => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    "flecha_der" => '<path d="M5 12h14M12 5l7 7-7 7"/>',
    "ticket" => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    "descargar" => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
    "ojo" => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    "ojo_no" => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.53 13.53 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20"/>',
    "check" => '<path d="M20 6 9 17l-5-5"/>',
    "check_circulo" => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
    "alerta" => '<path d="m10.29 3.86-8.47 14.14A2 2 0 0 0 3.53 21h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
    "reloj" => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    "calendario" => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    "ubicacion" => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
    "etiqueta" => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
    "bandera" => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
    "telefono" => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    "correo" => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    "clip" => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
    "comentario" => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    "cancelar" => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
    "candado" => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    "llave" => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
    "impresora" => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    "hoja" => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8M12 13v8"/>',
    "editar" => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    "basura" => '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    "encender" => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0M12 2v10"/>',
    "edificio" => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    "birrete" => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    "tarjeta" => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 10h4M14 14h4"/>',
    "rayo" => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
    "escudo" => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    "historial" => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l4 2"/>',
    "copiar" => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    "enviar" => '<path d="m22 2-7 20-4-9-9-4 20-7z"/><path d="M22 2 11 13"/>',
    "tendencia" => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
    "carpeta" => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
];

/*
 * Devuelve el SVG del ícono indicado (decorativo: aria-hidden).
 */
function icono($nombre, $clase = "icono")
{
    $trazos = ICONOS[$nombre] ?? ICONOS["carpeta"];

    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $trazos . '</svg>';
}

/*
 * Iniciales para el avatar: "María Hernández" -> "MH".
 */
function iniciales($nombre, $apellido = "")
{
    $letras = mb_substr(trim((string) $nombre), 0, 1) . mb_substr(trim((string) $apellido), 0, 1);

    return mb_strtoupper($letras !== "" ? $letras : "?");
}

/*
 * Estado vacío con ícono, título y texto opcional.
 */
function estadoVacio($titulo, $texto = "", $icono = "bandeja", $accion = "")
{
    return '<div class="vacio">'
        . '<span class="vacio-icono">' . icono($icono) . '</span>'
        . '<strong>' . e($titulo) . '</strong>'
        . ($texto !== "" ? '<p>' . e($texto) . '</p>' : '')
        . $accion
        . '</div>';
}

/*
 * Ícono que representa cada estado de una incidencia.
 */
function iconoEstado($estado)
{
    return [
        "Pendiente" => "reloj",
        "Ticket generado" => "ticket",
        "En revisión" => "ojo",
        "Asignada" => "usuario",
        "En proceso" => "rayo",
        "Resuelta" => "check_circulo",
        "Cerrada" => "candado",
        "Cancelada" => "cancelar",
    ][$estado] ?? "carpeta";
}

/*
 * Fecha larga en español: "martes 29 de septiembre de 2026".
 */
function fechaLarga($fecha = "now")
{
    $dias = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
    $meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio",
        "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

    $f = new DateTime($fecha);

    return $dias[(int) $f->format("w")] . " " . $f->format("j") . " de "
        . $meses[(int) $f->format("n") - 1] . " de " . $f->format("Y");
}

/*
 * Tono de color (para tarjetas e íconos) según el estado o la prioridad.
 * Reutiliza las clases de las etiquetas: "badge-pendiente" -> "tono-pendiente".
 */
function claseTono($claseBadge)
{
    return $claseBadge !== "" ? str_replace("badge-", "tono-", $claseBadge) : "tono-neutro";
}
