<?php

/*
|--------------------------------------------------------------------------
| PROTECCIONES GENERALES DE LA APLICACIÓN
|--------------------------------------------------------------------------
| Se carga desde app/helpers/auth.php, antes que cualquier otra cosa, en
| todas las páginas de public/. Aplica en un solo lugar:
|
|   1. Errores: nunca se muestran al navegador (solo van al log).
|   2. Rutas: rechaza URLs con PATH_INFO (pagina.php/loquesea).
|   3. Entradas: $_GET, $_POST y $_COOKIE solo contienen texto UTF-8 válido;
|      los arreglos (campo[]=..., campo[$ne]=...) se descartan, lo que evita
|      la inyección de operadores de MongoDB y los errores de tipo.
|   4. Cabeceras HTTP de seguridad (CSP, anti-clickjacking, nosniff, HSTS...).
|   5. Cookie de sesión endurecida (HttpOnly, SameSite, Secure con HTTPS,
|      modo estricto) y caducidad por inactividad y por duración máxima.
|
| Variables de entorno opcionales:
|   APP_DEBUG=1          muestra los errores (solo en desarrollo).
|   APP_FORZAR_HTTPS=1   marca la cookie como Secure y envía HSTS aunque PHP
|                        no detecte HTTPS (p. ej. detrás de un proxy inverso).
*/

/*
 * Tiempo máximo sin actividad antes de cerrar la sesión y duración
 * máxima de una sesión aunque haya actividad (en segundos).
 */
const SESION_INACTIVIDAD = 30 * 60;
const SESION_DURACION_MAXIMA = 8 * 60 * 60;

const SESION_NOMBRE = "TESCHI_SESION";

/*
 * Política de seguridad de contenido: solo recursos del propio sitio.
 * Los estilos en línea (atributos style="...") se permiten porque las
 * vistas los usan; los scripts en línea NO.
 */
const CABECERA_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
    . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

function modoDepuracion()
{
    return getenv("APP_DEBUG") === "1";
}

/*
 * true si la petición llegó por HTTPS.
 * No se confía en X-Forwarded-Proto porque el cliente puede falsificarla;
 * detrás de un proxy usa APP_FORZAR_HTTPS=1.
 */
function conexionSegura()
{
    if (getenv("APP_FORZAR_HTTPS") === "1") {
        return true;
    }

    $https = strtolower((string) ($_SERVER["HTTPS"] ?? ""));

    return ($https !== "" && $https !== "off") || (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443;
}

/*
|--------------------------------------------------------------------------
| 1. Errores
|--------------------------------------------------------------------------
*/

function configurarErrores()
{
    error_reporting(E_ALL);

    ini_set("display_errors", modoDepuracion() ? "1" : "0");
    ini_set("display_startup_errors", modoDepuracion() ? "1" : "0");
    ini_set("log_errors", "1");
    ini_set("html_errors", "0");

    if (modoDepuracion()) {
        return;
    }

    /*
     * Un error no controlado nunca debe mostrar rutas, consultas ni
     * cadenas de conexión: se registra y se muestra un mensaje genérico.
     */
    set_exception_handler(function ($e) {

        error_log(
            "Error no controlado: " . get_class($e) . ": " . $e->getMessage()
            . " en " . $e->getFile() . ":" . $e->getLine()
        );

        if (!headers_sent()) {
            http_response_code(500);
            header("Content-Type: text/plain; charset=utf-8");
        }

        echo "Ocurrió un error inesperado. Intenta de nuevo más tarde.";
    });
}

/*
|--------------------------------------------------------------------------
| 2. Rutas con PATH_INFO
|--------------------------------------------------------------------------
| "pagina.php/\\otro-sitio.com" ejecutaría pagina.php con una ruta falsa
| que acaba en enlaces y redirecciones. Ninguna página la necesita.
*/

function rechazarPathInfo()
{
    if ((string) ($_SERVER["PATH_INFO"] ?? "") !== "") {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        exit("Página no encontrada.");
    }
}

/*
|--------------------------------------------------------------------------
| 3. Normalización de entradas
|--------------------------------------------------------------------------
| Ningún formulario del sistema envía arreglos por GET/POST (los archivos
| llegan por $_FILES), así que cualquier valor que no sea texto se descarta.
| Con esto, un "correo[$ne]=x" nunca llega a un filtro de MongoDB.
*/

function limpiarEntrada(array $datos)
{
    $limpios = [];

    foreach ($datos as $clave => $valor) {

        if (!is_string($valor) || !mb_check_encoding((string) $clave, "UTF-8")) {
            continue;
        }

        $valor = str_replace("\0", "", $valor);

        if (!mb_check_encoding($valor, "UTF-8")) {
            $valor = mb_scrub($valor, "UTF-8");
        }

        $limpios[$clave] = $valor;
    }

    return $limpios;
}

function normalizarEntradas()
{
    $_GET = limpiarEntrada($_GET);
    $_POST = limpiarEntrada($_POST);
    $_COOKIE = limpiarEntrada($_COOKIE);
    $_REQUEST = array_merge($_GET, $_POST);
}

/*
|--------------------------------------------------------------------------
| 4. Cabeceras de seguridad
|--------------------------------------------------------------------------
*/

function enviarCabecerasSeguridad()
{
    if (headers_sent()) {
        return;
    }

    header_remove("X-Powered-By");

    header("Content-Security-Policy: " . CABECERA_CSP);
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: same-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-origin");

    if (conexionSegura()) {
        header("Strict-Transport-Security: max-age=31536000");
    }
}

/*
|--------------------------------------------------------------------------
| 5. Sesión
|--------------------------------------------------------------------------
*/

/*
 * La cookie solo se envía a la carpeta de la aplicación (public/), no a
 * otras aplicaciones que compartan el mismo servidor.
 */
function rutaCookieSesion()
{
    $carpeta = str_replace("\\", "/", dirname((string) ($_SERVER["SCRIPT_NAME"] ?? "/")));

    return rtrim($carpeta, "/") . "/";
}

function iniciarSesionSegura()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.gc_maxlifetime", (string) SESION_INACTIVIDAD);

    session_name(SESION_NOMBRE);

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => rutaCookieSesion(),
        "domain" => "",
        "secure" => conexionSegura(),
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();
}

/*
 * true si la sesión del usuario superó el tiempo de inactividad o la
 * duración máxima. Las sesiones sin marca de inicio (creadas antes de
 * esta protección) también se consideran caducadas.
 */
function sesionCaducada()
{
    if (!isset($_SESSION["usuario_id"])) {
        return false;
    }

    $ahora = time();
    $inicio = (int) ($_SESSION["sesion_inicio"] ?? 0);
    $actividad = (int) ($_SESSION["sesion_actividad"] ?? 0);

    return $inicio === 0
        || $ahora - $actividad > SESION_INACTIVIDAD
        || $ahora - $inicio > SESION_DURACION_MAXIMA;
}

/*
 * Vacía la sesión y le asigna un identificador nuevo (sigue abierta para
 * poder guardar mensajes flash, pero ya sin usuario).
 */
function reiniciarSesion()
{
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/*
 * Cierra la sesión por completo y borra la cookie del navegador.
 */
function cerrarSesion()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    $parametros = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $parametros["path"],
        "domain" => $parametros["domain"],
        "secure" => $parametros["secure"],
        "httponly" => $parametros["httponly"],
        "samesite" => $parametros["samesite"] ?? "Lax",
    ]);

    session_destroy();
}

/*
|--------------------------------------------------------------------------
| Arranque
|--------------------------------------------------------------------------
*/

configurarErrores();
rechazarPathInfo();
normalizarEntradas();
enviarCabecerasSeguridad();
iniciarSesionSegura();
