<?php

/*
|--------------------------------------------------------------------------
| FUNCIONES DE SESIÓN, ROLES Y SEGURIDAD
|--------------------------------------------------------------------------
| Se incluye al inicio de cada página protegida:
|
|     require_once "../app/helpers/auth.php";
|     requerirSesion();
|     requerirRol(["Administrador", "Coordinador"]);
*/

/*
 * Cabeceras de seguridad, entradas limpias y sesión endurecida.
 */
require_once __DIR__ . "/seguridad.php";

/*
 * Una sesión caducada (inactividad o duración máxima) se vacía aquí,
 * antes de que cualquier página la use.
 */
if (sesionCaducada()) {
    reiniciarSesion();
    flash("error", "Tu sesión expiró. Inicia sesión de nuevo.");
}

/*
 * Huella de la contraseña guardada en la sesión. Si la contraseña cambia
 * (desde Mi perfil, por el Administrador o con un enlace de
 * restablecimiento), las demás sesiones abiertas dejan de ser válidas.
 */
function huellaPassword($hash)
{
    return hash("sha256", "sesion|" . (string) $hash);
}

/*
 * Abre la sesión de un usuario recién autenticado.
 */
function iniciarSesionUsuario(array $usuario)
{
    /*
     * Nuevo identificador de sesión al iniciar sesión (evita la fijación
     * de sesión) y datos limpios (incluye un token CSRF nuevo).
     */
    session_regenerate_id(true);

    $_SESSION = [];

    $_SESSION["usuario_id"] = (int) $usuario["id"];
    $_SESSION["nombre"] = $usuario["nombre"];
    $_SESSION["apellido_paterno"] = $usuario["apellido_paterno"] ?? "";
    $_SESSION["correo"] = $usuario["correo"];
    $_SESSION["rol_id"] = (int) $usuario["rol_id"];
    $_SESSION["rol"] = $usuario["rol"];
    $_SESSION["huella"] = huellaPassword($usuario["password"]);
    $_SESSION["sesion_inicio"] = time();
    $_SESSION["sesion_actividad"] = time();
}

/*
 * Tras cambiar la contraseña del propio usuario: identificador nuevo y
 * huella nueva para que esta sesión siga abierta (las demás se cierran).
 */
function renovarSesionTrasCambioPassword($nuevoHash)
{
    session_regenerate_id(true);

    $_SESSION["huella"] = huellaPassword($nuevoHash);
}

/*
 * Documento del usuario en sesión si sigue activo y su contraseña no ha
 * cambiado desde que inició sesión; null en otro caso.
 */
function usuarioDeSesionVigente($db)
{
    if (!isset($_SESSION["usuario_id"])) {
        return null;
    }

    $usuario = $db->getCollection("usuarios")->findOne(
        ["_id" => (int) $_SESSION["usuario_id"], "activo" => true],
        ["projection" => ["rol_id" => 1, "password" => 1, "debe_cambiar_password" => 1]]
    );

    if (!$usuario || !hash_equals((string) ($_SESSION["huella"] ?? ""), huellaPassword($usuario["password"] ?? ""))) {
        return null;
    }

    return $usuario;
}

/*
 * Redirige al login si no hay una sesión activa.
 *
 * También vuelve a leer al usuario de la base de datos: si el
 * Administrador lo desactivó o su contraseña cambió, se cierra su
 * sesión, y si le cambió el rol, el cambio aplica de inmediato.
 */
function requerirSesion()
{
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit;
    }

    require_once __DIR__ . "/../config/database.php";

    $db = (new Database())->conectar();

    $usuario = usuarioDeSesionVigente($db);

    if (!$usuario) {
        reiniciarSesion();
        flash("error", "Tu sesión se cerró. Inicia sesión de nuevo.");
        header("Location: login.php");
        exit;
    }

    $rol = $db->getCollection("roles")->findOne(
        ["_id" => (int) $usuario["rol_id"]],
        ["projection" => ["nombre" => 1]]
    );

    $_SESSION["rol_id"] = (int) $usuario["rol_id"];
    $_SESSION["rol"] = $rol["nombre"] ?? "";
    $_SESSION["sesion_actividad"] = time();

    /*
     * Contraseña asignada por el Administrador (o de ejemplo): hay que
     * cambiarla antes de usar el sistema.
     */
    if (!empty($usuario["debe_cambiar_password"]) && basename((string) $_SERVER["SCRIPT_NAME"]) !== "perfil.php") {
        flash("error", "Por seguridad, cambia tu contraseña antes de continuar.");
        header("Location: perfil.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| POLÍTICA DE CONTRASEÑAS
|--------------------------------------------------------------------------
*/

const PASSWORD_MIN = 8;
const PASSWORD_MAX_BYTES = 72; // bcrypt ignora lo que pase de 72 bytes

/*
 * Contraseñas demasiado comunes y las de ejemplo que aparecían en la
 * documentación (se comparan en minúsculas).
 */
const PASSWORDS_PROHIBIDAS = [
    "admin1234", "demo1234", "admin123", "admin12345", "administrador1",
    "password1", "password12", "password123", "contraseña1", "contrasena1", "contraseña123", "contrasena123",
    "12345678a", "a1234567", "1234567a", "abc12345", "abcd1234", "qwerty123", "qwerty12", "1q2w3e4r",
    "usuario1", "usuario123", "teschi123", "teschi1234", "teschi2024", "teschi2025", "teschi2026",
];

/*
 * Devuelve el mensaje de error o null si la contraseña es aceptable.
 */
function validarPassword($password, $correo = "")
{
    if (!is_string($password) || mb_strlen($password) < PASSWORD_MIN) {
        return "La contraseña debe tener al menos " . PASSWORD_MIN . " caracteres.";
    }

    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return "La contraseña admite máximo " . PASSWORD_MAX_BYTES . " caracteres.";
    }

    if (!preg_match('/\pL/u', $password) || !preg_match('/\d/', $password)) {
        return "La contraseña debe combinar letras y números.";
    }

    $minusculas = mb_strtolower($password);
    $correo = mb_strtolower((string) $correo);

    if ($correo !== "" && ($minusculas === $correo || $minusculas === strstr($correo, "@", true))) {
        return "La contraseña no puede ser tu correo.";
    }

    if (in_array($minusculas, PASSWORDS_PROHIBIDAS, true)) {
        return "Esa contraseña es demasiado común o es de ejemplo. Elige otra.";
    }

    return null;
}

/*
 * true si la contraseña ya no cumple la política (se usa al iniciar
 * sesión para obligar a cambiarla).
 */
function passwordDebil($password, $correo = "")
{
    return validarPassword($password, $correo) !== null;
}

/*
 * Permite el acceso únicamente a los roles indicados.
 */
function requerirRol(array $roles)
{
    requerirSesion();

    if (!in_array($_SESSION["rol"], $roles, true)) {
        header("Location: dashboard.php");
        exit;
    }
}

/*
 * Indica si el usuario en sesión tiene alguno de los roles indicados.
 * Ejemplo: tieneRol("Administrador", "Coordinador")
 */
function tieneRol(...$roles)
{
    return isset($_SESSION["rol"]) && in_array($_SESSION["rol"], $roles, true);
}

/*
 * Roles que gestionan incidencias (ven todas, cambian estados, asignan).
 */
function esGestor()
{
    return tieneRol("Administrador", "Coordinador");
}

/*
 * Escapa texto para imprimirlo en HTML.
 */
function e($valor)
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

/*
 * Token CSRF: evita que otro sitio envíe formularios en nombre del usuario.
 */
function csrfToken()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function campoCsrf()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verificarCsrf()
{
    $token = $_POST["csrf_token"] ?? "";

    return is_string($token) && hash_equals(csrfToken(), $token);
}

/*
 * Mensajes que sobreviven a una redirección (patrón Post/Redirect/Get).
 */
function flash($tipo, $mensaje)
{
    $_SESSION["flash"] = [
        "tipo" => $tipo,
        "mensaje" => $mensaje
    ];
}

function obtenerFlash()
{
    $flash = $_SESSION["flash"] ?? null;

    unset($_SESSION["flash"]);

    return $flash;
}

/*
 * Clase CSS para pintar el estado de una incidencia como etiqueta.
 */
function claseEstado($estado)
{
    $clases = [
        "Pendiente" => "badge-pendiente",
        "En revisión" => "badge-revision",
        "Asignada" => "badge-asignada",
        "En proceso" => "badge-proceso",
        "Resuelta" => "badge-resuelta",
        "Cerrada" => "badge-cerrada",
        "Cancelada" => "badge-cancelada"
    ];

    return $clases[$estado] ?? "";
}

function clasePrioridad($prioridad)
{
    $clases = [
        "Baja" => "badge-baja",
        "Media" => "badge-media",
        "Alta" => "badge-alta",
        "Crítica" => "badge-critica"
    ];

    return $clases[$prioridad] ?? "";
}

/*
 * true si el formulario superó post_max_size: en ese caso PHP descarta
 * todo el envío ($_POST y $_FILES llegan vacíos).
 */
function peticionDemasiadoGrande()
{
    return $_SERVER["REQUEST_METHOD"] === "POST"
        && empty($_POST)
        && empty($_FILES)
        && (int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 0;
}

function mensajePeticionDemasiadoGrande()
{
    return "Los archivos enviados superan el límite del servidor ("
        . ini_get("post_max_size") . " en total). Adjunta menos o más ligeros.";
}
