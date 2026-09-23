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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Redirige al login si no hay una sesión activa.
 *
 * También vuelve a leer al usuario de la base de datos: si el
 * Administrador lo desactivó, se cierra su sesión, y si le cambió
 * el rol, el cambio aplica de inmediato.
 */
function requerirSesion()
{
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit;
    }

    require_once __DIR__ . "/../config/database.php";

    $database = new Database();

    $stmt = $database->conectar()->prepare("
        SELECT r.id AS rol_id, r.nombre AS rol
        FROM usuarios u
        INNER JOIN roles r
            ON u.rol_id = r.id
        WHERE u.id = ?
        AND u.activo = 1
    ");

    $stmt->execute([$_SESSION["usuario_id"]]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $_SESSION["rol_id"] = $usuario["rol_id"];
    $_SESSION["rol"] = $usuario["rol"];
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
