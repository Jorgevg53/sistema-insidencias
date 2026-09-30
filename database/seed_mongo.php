<?php

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN DE LA BASE DE DATOS MONGODB
|--------------------------------------------------------------------------
| Crea las colecciones, los índices, los contadores (autoincrement) y los
| datos iniciales del sistema (equivale a importar database.sql en MySQL).
|
| Uso desde la terminal (la carpeta database/ está protegida y no se abre
| desde el navegador):
|     C:\xampp\php\php.exe database\seed_mongo.php
|     C:\xampp\php\php.exe database\seed_mongo.php force   (reinicia los datos)
|
| En Linux/Mac:  php database/seed_mongo.php
|
| Requiere la extensión "mongodb" de PHP y un servidor MongoDB en ejecución.
| La conexión se toma de app/config/database.php (o de MONGODB_URI / MONGODB_DB).
*/

require_once __DIR__ . "/../app/config/database.php";

$enCli = PHP_SAPI === "cli";
$salto = $enCli ? "\n" : "<br>\n";

if (!$enCli) {
    header("Content-Type: text/plain; charset=utf-8");
}

function paso($texto)
{
    global $salto;
    echo $texto . $salto;
}

$forzar = ($enCli && in_array("force", $argv, true)) || (($_GET["force"] ?? "") === "1");

/*
 * Contraseñas iniciales (cámbialas después desde "Mi perfil").
 */
$PASS_ADMIN = "Admin1234";
$PASS_DEMO = "Demo1234";

try {
    $db = (new Database())->conectar();
} catch (Throwable $e) {
    paso("ERROR: " . $e->getMessage());
    exit(1);
}

/*
 * Si ya hay usuarios y no se indicó "force", no se hace nada
 * (para no borrar datos por accidente).
 */
if (!$forzar && $db->getCollection("usuarios")->countDocuments() > 0) {
    paso("La base ya tiene datos. Si quieres reiniciarla, ejecuta:");
    paso($enCli ? "    php database/seed_mongo.php force" : "    Agrega ?force=1 a la URL.");
    exit;
}

$colecciones = [
    "roles", "estados", "categorias", "prioridades",
    "usuarios", "incidencias", "notificaciones", "restablecimientos", "contadores",
];

paso("Limpiando colecciones anteriores...");
foreach ($colecciones as $c) {
    $db->getCollection($c)->drop();
}

/*
|--------------------------------------------------------------------------
| Catálogos
|--------------------------------------------------------------------------
*/

$roles = [
    ["_id" => 1, "nombre" => "Administrador", "descripcion" => "Gestiona todas las incidencias del sistema de calificaciones", "activo" => true],
    ["_id" => 2, "nombre" => "Docente", "descripcion" => "Reporta y da seguimiento a incidencias del sistema de calificaciones", "activo" => true],
];
$db->getCollection("roles")->insertMany($roles);

$estados = [
    ["_id" => 1, "nombre" => "Pendiente", "descripcion" => "La incidencia fue registrada y está pendiente de revisión"],
    ["_id" => 8, "nombre" => "Ticket generado", "descripcion" => "Se generó el ticket de la incidencia y está en espera de revisión"],
    ["_id" => 2, "nombre" => "En revisión", "descripcion" => "La incidencia está siendo revisada"],
    ["_id" => 3, "nombre" => "Asignada", "descripcion" => "La incidencia fue asignada a un responsable"],
    ["_id" => 4, "nombre" => "En proceso", "descripcion" => "La incidencia está siendo atendida"],
    ["_id" => 5, "nombre" => "Resuelta", "descripcion" => "La incidencia fue solucionada"],
    ["_id" => 6, "nombre" => "Cerrada", "descripcion" => "La incidencia fue finalizada"],
    ["_id" => 7, "nombre" => "Cancelada", "descripcion" => "La incidencia fue cancelada"],
];
$db->getCollection("estados")->insertMany($estados);

/*
 * Tipos de incidencia: únicamente los del sistema de calificaciones.
 * (La colección se sigue llamando "categorias" por compatibilidad.)
 */
$categorias = [
    [1, "No puedo iniciar sesión", "Usuario o contraseña no válidos, cuenta bloqueada o sin acceso al sistema"],
    [2, "Lista de alumnos incorrecta", "Faltan alumnos, sobran alumnos o el grupo no corresponde"],
    [3, "No aparece mi materia o grupo", "La materia o el grupo no está asignado al docente en el periodo"],
    [4, "Error al capturar calificaciones", "El sistema marca error o no guarda las calificaciones capturadas"],
    [5, "Calificación mal registrada", "Una calificación ya capturada aparece con un valor distinto"],
    [6, "Corrección de calificación", "Solicitud para modificar una calificación ya cerrada o enviada"],
    [7, "Captura fuera de fecha", "Solicitud de apertura del periodo de captura por extemporaneidad"],
    [8, "Acta o reporte de calificaciones", "Error al generar, imprimir o descargar actas y reportes"],
    [9, "Otra del sistema de calificaciones", "Cualquier otra situación del sistema de calificaciones"],
];
$db->getCollection("categorias")->insertMany(array_map(
    fn($c) => ["_id" => $c[0], "nombre" => $c[1], "descripcion" => $c[2], "activo" => true],
    $categorias
));

$prioridades = [
    [1, "Baja", 1, 3],
    [2, "Media", 2, 2],
    [3, "Alta", 3, 2],
    [4, "Crítica", 4, 1],
];
$db->getCollection("prioridades")->insertMany(array_map(
    fn($p) => ["_id" => $p[0], "nombre" => $p[1], "nivel" => $p[2], "dias_atencion" => $p[3], "activo" => true],
    $prioridades
));

paso("Catálogos creados (roles, estados, categorías, prioridades).");

/*
|--------------------------------------------------------------------------
| Usuarios
|--------------------------------------------------------------------------
*/

$hoy = date("Y-m-d H:i:s");

/*
 * 2 administradores y docentes de ejemplo (uno por carrera de muestra).
 * Sustituye los docentes de ejemplo por los reales desde "Usuarios".
 */
$usuarios = [
    [1, "ADM-0001", "Administrador", "Principal", "", "admin@teschi.edu.mx", $PASS_ADMIN, 1, "Ciencias Básicas", "No aplica", "55 5000 0001"],
    [2, "ADM-0002", "Administrador", "Suplente", "", "admin2@teschi.edu.mx", $PASS_ADMIN, 1, "Ciencias Básicas", "No aplica", "55 5000 0002"],
    [3, "DOC-0457", "Juan", "Pérez", "Rojas", "jperez@teschi.edu.mx", $PASS_DEMO, 2, "Ciencias Básicas", "Ingeniería en Sistemas Computacionales", "55 1234 5678"],
    [4, "DOC-0311", "Roberto", "Sánchez", "Díaz", "rsanchez@teschi.edu.mx", $PASS_DEMO, 2, "Ciencias Básicas", "Ingeniería Mecatrónica", "55 2345 6789"],
    [5, "DOC-0522", "Ana", "López", "García", "alopez@teschi.edu.mx", $PASS_DEMO, 2, "Ciencias Básicas", "Ingeniería Industrial", "55 4567 8901"],
];
$db->getCollection("usuarios")->insertMany(array_map(
    fn($u) => [
        "_id" => $u[0],
        "matricula" => $u[1],
        "nombre" => $u[2],
        "apellido_paterno" => $u[3],
        "apellido_materno" => $u[4],
        "correo" => $u[5],
        "password" => password_hash($u[6], PASSWORD_DEFAULT),
        "rol_id" => $u[7],
        "departamento" => $u[8] !== "" ? $u[8] : null,
        "carrera" => $u[9],
        "telefono" => $u[10],
        "activo" => true,
        "fecha_registro" => $hoy,
    ],
    $usuarios
));

paso(count($usuarios) . " usuarios creados.");

/*
|--------------------------------------------------------------------------
| Incidencias de ejemplo (con historial embebido)
|--------------------------------------------------------------------------
*/

$hid = 0;
$evento = function ($usuario_id, $accion, $descripcion, $fecha, $ant = null, $nue = null) use (&$hid) {
    return [
        "id" => ++$hid,
        "usuario_id" => $usuario_id,
        "accion" => $accion,
        "estado_anterior_id" => $ant,
        "estado_nuevo_id" => $nue,
        "descripcion" => $descripcion,
        "fecha" => $fecha,
    ];
};

$fecha = fn($dias, $horas = 0) => date("Y-m-d H:i:s", strtotime("-$dias days +$horas hours"));

$incidencias = [];

$caso = function ($id, $sufijo, $usuario, $cat, $prio, $estado, $titulo, $desc, $materia, $carrera, $tel, $resp, $reg, $act, $cierre, $hist, $coment = []) {
    return [
        "_id" => $id, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-" . $sufijo,
        "usuario_id" => $usuario, "categoria_id" => $cat, "prioridad_id" => $prio, "estado" => $estado,
        "titulo" => $titulo, "descripcion" => $desc, "ubicacion" => $materia, "carrera" => $carrera,
        "telefono_contacto" => $tel, "responsable_id" => $resp,
        "fecha_registro" => $reg, "fecha_actualizacion" => $act, "fecha_cierre" => $cierre,
        "historial" => $hist, "comentarios" => $coment, "evidencias" => [],
    ];
};

// 1) En proceso
$reg = $fecha(3);
$incidencias[] = $caso(1, "A1B2C3", 3, 4, 3, "En proceso",
    "No se guardan las calificaciones del parcial 2",
    "Al pulsar Guardar en la captura del segundo parcial aparece un error y las calificaciones no se conservan.",
    "Programación Orientada a Objetos · Grupo 3501", "Ingeniería en Sistemas Computacionales", "55 1234 5678",
    1, $reg, $fecha(2), null, [
        $evento(3, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(1, "asignacion", "Responsable asignado: Administrador Principal", $fecha(3, 2)),
        $evento(1, "estado", "Estado: Asignada → En proceso", $fecha(2), 3, 4),
    ]);

// 2) Asignada
$reg = $fecha(2);
$incidencias[] = $caso(2, "D4E5F6", 5, 2, 4, "Asignada",
    "Faltan alumnos en la lista del grupo",
    "Tres alumnos inscritos no aparecen en la lista para capturar calificaciones.",
    "Procesos de Manufactura · Grupo 4201", "Ingeniería Industrial", "55 4567 8901",
    2, $reg, $fecha(2, 1), null, [
        $evento(5, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(1, "asignacion", "Responsable asignado: Administrador Suplente", $fecha(2, 1)),
    ]);

// 3) Resuelta
$reg = $fecha(10);
$cierre = $fecha(7);
$incidencias[] = $caso(3, "77AA88", 3, 1, 2, "Resuelta",
    "No puedo iniciar sesión en el sistema de calificaciones",
    "El sistema indica que mi contraseña es incorrecta aunque no la he cambiado.",
    "", "Ingeniería en Sistemas Computacionales", "55 1234 5678",
    2, $reg, $cierre, $cierre, [
        $evento(3, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(1, "asignacion", "Responsable asignado: Administrador Suplente", $fecha(10, 3)),
        $evento(2, "estado", "Estado: Asignada → En proceso", $fecha(9), 3, 4),
        $evento(2, "estado", "Estado: En proceso → Resuelta", $cierre, 4, 5),
    ], [
        ["id" => 1, "usuario_id" => 2, "comentario" => "Se restableció la contraseña y se verificó el acceso.", "fecha" => $cierre],
    ]);

// 4) Pendiente
$reg = $fecha(1);
$incidencias[] = $caso(4, "99CC00", 4, 6, 2, "Pendiente",
    "Corrección de una calificación ya enviada",
    "Se capturó 6 en lugar de 8 en la unidad 3 de un alumno; solicito la corrección.",
    "Dinámica · Grupo 2301", "Ingeniería Mecatrónica", "55 2345 6789",
    null, $reg, $reg, null, [
        $evento(4, "registro", "Incidencia registrada", $reg, null, 1),
    ]);

// 5) Cerrada
$reg = $fecha(20);
$cierre = $fecha(15);
$incidencias[] = $caso(5, "1A2B3C", 5, 8, 1, "Cerrada",
    "El acta de calificaciones no se descarga",
    "Al generar el acta final del grupo el archivo sale en blanco.",
    "Estadística · Grupo 3101", "Ingeniería Industrial", "55 4567 8901",
    1, $reg, $cierre, $fecha(16), [
        $evento(5, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(1, "asignacion", "Responsable asignado: Administrador Principal", $fecha(19)),
        $evento(1, "estado", "Estado: Asignada → En proceso", $fecha(18), 3, 4),
        $evento(1, "estado", "Estado: En proceso → Resuelta", $fecha(16), 4, 5),
        $evento(1, "estado", "Estado: Resuelta → Cerrada", $cierre, 5, 6),
    ]);

$db->getCollection("incidencias")->insertMany($incidencias);

paso(count($incidencias) . " incidencias de ejemplo creadas.");

/*
|--------------------------------------------------------------------------
| Contadores (autoincrement) — el siguiente id continúa después del último
|--------------------------------------------------------------------------
*/

$contadores = [
    ["_id" => "usuarios", "seq" => count($usuarios)],
    ["_id" => "incidencias", "seq" => count($incidencias)],
    ["_id" => "categorias", "seq" => count($categorias)],
    ["_id" => "prioridades", "seq" => count($prioridades)],
    ["_id" => "historial", "seq" => $hid],
    ["_id" => "comentarios", "seq" => 1],
    ["_id" => "evidencias", "seq" => 0],
    ["_id" => "notificaciones", "seq" => 0],
    ["_id" => "restablecimientos", "seq" => 0],
];
$db->getCollection("contadores")->insertMany($contadores);

/*
|--------------------------------------------------------------------------
| Índices
|--------------------------------------------------------------------------
*/

$db->getCollection("usuarios")->createIndex(["correo" => 1], ["unique" => true]);
$db->getCollection("incidencias")->createIndex(["folio" => 1], ["unique" => true]);
$db->getCollection("incidencias")->createIndex(["usuario_id" => 1]);
$db->getCollection("incidencias")->createIndex(["responsable_id" => 1]);
$db->getCollection("incidencias")->createIndex(["estado" => 1]);
$db->getCollection("incidencias")->createIndex(["fecha_registro" => -1]);
$db->getCollection("incidencias")->createIndex(["evidencias.id" => 1]);
$db->getCollection("notificaciones")->createIndex(["usuario_id" => 1, "leida" => 1]);
$db->getCollection("notificaciones")->createIndex(["incidencia_id" => 1]);
$db->getCollection("restablecimientos")->createIndex(["token_hash" => 1]);
$db->getCollection("categorias")->createIndex(["nombre" => 1], ["unique" => true]);
$db->getCollection("prioridades")->createIndex(["nombre" => 1], ["unique" => true]);

paso("Índices creados.");
paso("");
paso("¡Base de datos MongoDB lista!");
paso("");
paso("Cuentas para iniciar sesión:");
paso("  Administrador  ->  admin@teschi.edu.mx      /  " . $PASS_ADMIN);
paso("  Administrador  ->  admin2@teschi.edu.mx     /  " . $PASS_ADMIN);
paso("  Docente        ->  jperez@teschi.edu.mx     /  " . $PASS_DEMO);
paso("  Docente        ->  rsanchez@teschi.edu.mx   /  " . $PASS_DEMO);
paso("  Docente        ->  alopez@teschi.edu.mx     /  " . $PASS_DEMO);
paso("");
paso("Cambia estas contraseñas desde «Mi perfil» al terminar de probar.");
