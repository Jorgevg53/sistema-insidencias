<?php

/*
|--------------------------------------------------------------------------
| DATOS DE LA INSTITUCIÓN PARA EL TICKET EN PDF
|--------------------------------------------------------------------------
| Edita estos textos para cambiar el encabezado y las leyendas del ticket.
|
| Logotipos del encabezado: public/img/logo_edomex.png (izquierda) y
| public/img/logo_teschi.png (derecha); ver "logo_izquierdo" y "logo_derecho".
| Si existe, se imprime en la esquina superior izquierda del ticket.
*/

return [
    "nombre" => "TECNOLÓGICO DE ESTUDIOS SUPERIORES DE CHIMALHUACÁN",
    "direccion" => "C. ÓPALO 18, STA. MARÍA NATIVITAS, 56335",
    "ciudad" => "CHIMALHUACÁN, MÉX.",
    "departamento" => "Departamento de Ciencias Básicas",

    /*
     * Logotipos del encabezado del ticket (PNG o JPG; FPDF no lee WebP).
     * Para quitar uno, deja la ruta vacía: "".
     */
    "logo_izquierdo" => __DIR__ . "/../../public/img/logo_edomex.png",  // Gobierno del Estado de México
    "logo_derecho" => __DIR__ . "/../../public/img/logo_teschi.png",    // TESCHI

    /*
     * Carreras que ofrece la institución (lista desplegable al registrar
     * incidencias, en Mi perfil y en Usuarios; también en Reportes).
     */
    "carreras" => [
        "Ingeniería en Animación Digital y Efectos Visuales",
        "Ingeniería en Sistemas Computacionales",
        "Ingeniería Industrial",
        "Ingeniería Mecatrónica",
        "Ingeniería Química",
        "Licenciatura en Administración",
        "Licenciatura en Gastronomía",
        "Posgrado en Administración",
    ],

    "leyenda_garantia" => "CONSERVE ESTE TICKET PARA GARANTÍA",
    "leyenda_aclaracion" => "Cualquier aclaración o rectificación, acercarse al Departamento de Ciencias Básicas.",
    "firma_departamento" => "Firma del Depto. Ciencias Básicas",
    "firma_atendido" => "Nombre y firma de la persona atendida",
    "despedida" => "GRACIAS POR SU VISITA",
];
