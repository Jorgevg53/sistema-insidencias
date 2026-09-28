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
    ["_id" => 1, "nombre" => "Administrador", "descripcion" => "Acceso completo al sistema", "activo" => true],
    ["_id" => 2, "nombre" => "Coordinador", "descripcion" => "Gestión de incidencias del Departamento de Ciencias Básicas", "activo" => true],
    ["_id" => 3, "nombre" => "Docente", "descripcion" => "Registro y seguimiento de incidencias docentes", "activo" => true],
    ["_id" => 4, "nombre" => "Administrativo", "descripcion" => "Registro y seguimiento de incidencias administrativas", "activo" => true],
    ["_id" => 5, "nombre" => "Estudiante", "descripcion" => "Registro y consulta de incidencias", "activo" => true],
];
$db->getCollection("roles")->insertMany($roles);

$estados = [
    ["_id" => 1, "nombre" => "Pendiente", "descripcion" => "La incidencia fue registrada y está pendiente de revisión"],
    ["_id" => 2, "nombre" => "En revisión", "descripcion" => "La incidencia está siendo revisada"],
    ["_id" => 3, "nombre" => "Asignada", "descripcion" => "La incidencia fue asignada a un responsable"],
    ["_id" => 4, "nombre" => "En proceso", "descripcion" => "La incidencia está siendo atendida"],
    ["_id" => 5, "nombre" => "Resuelta", "descripcion" => "La incidencia fue solucionada"],
    ["_id" => 6, "nombre" => "Cerrada", "descripcion" => "La incidencia fue finalizada"],
    ["_id" => 7, "nombre" => "Cancelada", "descripcion" => "La incidencia fue cancelada"],
];
$db->getCollection("estados")->insertMany($estados);

$categorias = [
    [1, "Académica", "Incidencias relacionadas con actividades académicas"],
    [2, "Administrativa", "Incidencias relacionadas con procesos administrativos"],
    [3, "Infraestructura", "Problemas relacionados con instalaciones"],
    [4, "Equipo de cómputo", "Problemas con computadoras o dispositivos"],
    [5, "Software", "Problemas relacionados con sistemas o aplicaciones"],
    [6, "Redes", "Problemas relacionados con conectividad y redes"],
    [7, "Control Escolar", "Incidencias relacionadas con Control Escolar"],
    [8, "Otra", "Otras incidencias"],
];
$db->getCollection("categorias")->insertMany(array_map(
    fn($c) => ["_id" => $c[0], "nombre" => $c[1], "descripcion" => $c[2], "activo" => true],
    $categorias
));

$prioridades = [
    [1, "Baja", 1, 5],
    [2, "Media", 2, 3],
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

$usuarios = [
    [1, "ADM-0001", "Administrador", "TESCHI", "", "admin@teschi.edu.mx", $PASS_ADMIN, 1, "Ciencias Básicas", "No aplica", "55 5000 0001"],
    [2, "COO-0102", "María", "Hernández", "López", "mhernandez@teschi.edu.mx", $PASS_DEMO, 2, "Ciencias Básicas", "No aplica", "55 5000 0102"],
    [3, "DOC-0457", "Juan", "Pérez", "Rojas", "jperez@teschi.edu.mx", $PASS_DEMO, 3, "Ciencias Básicas", "Ingeniería en Sistemas Computacionales", "55 1234 5678"],
    [4, "DOC-0311", "Roberto", "Sánchez", "Díaz", "rsanchez@teschi.edu.mx", $PASS_DEMO, 3, "Ciencias Básicas", "Ingeniería Mecatrónica", "55 2345 6789"],
    [5, "202312045", "Ana", "López", "García", "alopez@teschi.edu.mx", $PASS_DEMO, 5, "", "Ingeniería Industrial", "55 4567 8901"],
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

// 1) En proceso
$reg = $fecha(3);
$incidencias[] = [
    "_id" => 1, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-A1B2C3",
    "usuario_id" => 3, "categoria_id" => 4, "prioridad_id" => 3, "estado" => "En proceso",
    "titulo" => "Proyector sin imagen en Aula 12",
    "descripcion" => "El proyector enciende pero no muestra imagen al conectar la laptop por HDMI. Se probó con dos equipos distintos.",
    "ubicacion" => "Edificio B · Aula 12", "carrera" => "Ingeniería en Sistemas Computacionales",
    "telefono_contacto" => "55 1234 5678", "responsable_id" => 4,
    "fecha_registro" => $reg, "fecha_actualizacion" => $fecha(2), "fecha_cierre" => null,
    "historial" => [
        $evento(3, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(2, "asignacion", "Responsable asignado: Roberto Sánchez", $fecha(3, 2)),
        $evento(4, "estado", "Estado: Asignada → En proceso", $fecha(2), 3, 4),
    ],
    "comentarios" => [], "evidencias" => [],
];

// 2) Asignada
$reg = $fecha(2);
$incidencias[] = [
    "_id" => 2, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-D4E5F6",
    "usuario_id" => 5, "categoria_id" => 6, "prioridad_id" => 4, "estado" => "Asignada",
    "titulo" => "Falla de red en Laboratorio de cómputo 2",
    "descripcion" => "Ningún equipo del laboratorio tiene acceso a internet desde la mañana.",
    "ubicacion" => "Laboratorio de cómputo 2", "carrera" => "Ingeniería Industrial",
    "telefono_contacto" => "55 4567 8901", "responsable_id" => 4,
    "fecha_registro" => $reg, "fecha_actualizacion" => $fecha(2, 1), "fecha_cierre" => null,
    "historial" => [
        $evento(5, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(2, "asignacion", "Responsable asignado: Roberto Sánchez", $fecha(2, 1)),
    ],
    "comentarios" => [], "evidencias" => [],
];

// 3) Resuelta
$reg = $fecha(10);
$cierre = $fecha(7);
$incidencias[] = [
    "_id" => 3, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-77AA88",
    "usuario_id" => 3, "categoria_id" => 6, "prioridad_id" => 2, "estado" => "Resuelta",
    "titulo" => "No hay internet en la sala de maestros",
    "descripcion" => "La sala de maestros perdió la conexión Wi-Fi.",
    "ubicacion" => "Sala de maestros", "carrera" => "Ingeniería en Sistemas Computacionales",
    "telefono_contacto" => "55 1234 5678", "responsable_id" => 4,
    "fecha_registro" => $reg, "fecha_actualizacion" => $cierre, "fecha_cierre" => $cierre,
    "historial" => [
        $evento(3, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(2, "asignacion", "Responsable asignado: Roberto Sánchez", $fecha(10, 3)),
        $evento(4, "estado", "Estado: Asignada → En proceso", $fecha(9), 3, 4),
        $evento(4, "estado", "Estado: En proceso → Resuelta", $cierre, 4, 5),
    ],
    "comentarios" => [
        ["id" => 1, "usuario_id" => 4, "comentario" => "Se reinició el punto de acceso y se verificó la conexión.", "fecha" => $cierre],
    ],
    "evidencias" => [],
];

// 4) Pendiente
$reg = $fecha(1);
$incidencias[] = [
    "_id" => 4, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-99CC00",
    "usuario_id" => 5, "categoria_id" => 3, "prioridad_id" => 1, "estado" => "Pendiente",
    "titulo" => "Silla dañada en la Biblioteca",
    "descripcion" => "Una silla del área de lectura está rota y es insegura.",
    "ubicacion" => "Biblioteca", "carrera" => "Ingeniería Industrial",
    "telefono_contacto" => "55 4567 8901", "responsable_id" => null,
    "fecha_registro" => $reg, "fecha_actualizacion" => $reg, "fecha_cierre" => null,
    "historial" => [
        $evento(5, "registro", "Incidencia registrada", $reg, null, 1),
    ],
    "comentarios" => [], "evidencias" => [],
];

// 5) Cerrada
$reg = $fecha(20);
$cierre = $fecha(15);
$incidencias[] = [
    "_id" => 5, "folio" => "INC-" . date("Ymd", strtotime($reg)) . "-1A2B3C",
    "usuario_id" => 3, "categoria_id" => 5, "prioridad_id" => 2, "estado" => "Cerrada",
    "titulo" => "Software de simulación sin licencia",
    "descripcion" => "El software de simulación pide una licencia al abrir.",
    "ubicacion" => "Laboratorio de cómputo 1", "carrera" => "Ingeniería en Sistemas Computacionales",
    "telefono_contacto" => "55 1234 5678", "responsable_id" => 2,
    "fecha_registro" => $reg, "fecha_actualizacion" => $cierre, "fecha_cierre" => $fecha(16),
    "historial" => [
        $evento(3, "registro", "Incidencia registrada", $reg, null, 1),
        $evento(2, "asignacion", "Responsable asignado: María Hernández", $fecha(19)),
        $evento(2, "estado", "Estado: Asignada → En proceso", $fecha(18), 3, 4),
        $evento(2, "estado", "Estado: En proceso → Resuelta", $fecha(16), 4, 5),
        $evento(2, "estado", "Estado: Resuelta → Cerrada", $cierre, 5, 6),
    ],
    "comentarios" => [], "evidencias" => [],
];

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
paso("  Coordinador    ->  mhernandez@teschi.edu.mx /  " . $PASS_DEMO);
paso("  Docente        ->  jperez@teschi.edu.mx     /  " . $PASS_DEMO);
paso("  Docente        ->  rsanchez@teschi.edu.mx   /  " . $PASS_DEMO);
paso("  Estudiante     ->  alopez@teschi.edu.mx     /  " . $PASS_DEMO);
paso("");
paso("Cambia estas contraseñas desde «Mi perfil» al terminar de probar.");
