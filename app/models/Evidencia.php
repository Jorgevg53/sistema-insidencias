<?php

/*
|--------------------------------------------------------------------------
| EVIDENCIAS (ARCHIVOS ADJUNTOS)
|--------------------------------------------------------------------------
| - Se guardan en storage/evidencias/ (fuera de public/) con un nombre
|   aleatorio; nunca con el nombre que envió el usuario.
| - El tipo se detecta por el CONTENIDO del archivo, no por la extensión.
| - Solo se descargan mediante public/evidencia.php, que revisa permisos.
*/

class Evidencia
{
    const MAX_ARCHIVOS = 5;
    const MAX_BYTES = 5 * 1024 * 1024; // 5 MB por archivo

    /*
     * Tipos permitidos => extensión con la que se guardan.
     */
    const TIPOS = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "application/pdf" => "pdf"
    ];

    private $conn;

    /*
     * Archivos ya movidos en esta petición (para borrarlos si
     * la transacción se revierte).
     */
    private $movidos = [];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public static function directorio()
    {
        return dirname(__DIR__, 2) . "/storage/evidencias";
    }

    public static function ruta($archivo)
    {
        return self::directorio() . "/" . basename($archivo);
    }

    /*
     * Lee y valida el campo <input type="file" name="evidencias[]" multiple>.
     * Devuelve [archivos válidos, mensaje de error o null].
     */
    public static function validarSubida($campo = "evidencias")
    {
        if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]["name"])) {
            return [[], null];
        }

        $subidos = $_FILES[$campo];
        $archivos = [];

        foreach ($subidos["name"] as $i => $nombre) {

            $codigo = $subidos["error"][$i];

            if ($codigo === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $nombre = self::limpiarNombre($nombre);

            if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
                return [[], "«{$nombre}» supera el tamaño permitido por el servidor ("
                    . ini_get("upload_max_filesize") . ")."];
            }

            if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($subidos["tmp_name"][$i])) {
                return [[], "No se pudo recibir «{$nombre}». Intenta de nuevo."];
            }

            if ($subidos["size"][$i] > self::MAX_BYTES) {
                return [[], "«{$nombre}» pesa más de " . self::formatearTamano(self::MAX_BYTES) . "."];
            }

            $tipo = self::detectarTipo($subidos["tmp_name"][$i]);

            if (!isset(self::TIPOS[$tipo])) {
                return [[], "«{$nombre}» no es un tipo permitido. Usa JPG, PNG, WEBP o PDF."];
            }

            $archivos[] = [
                "nombre" => $nombre,
                "tmp" => $subidos["tmp_name"][$i],
                "tipo" => $tipo,
                "tamano" => (int) $subidos["size"][$i]
            ];
        }

        if (count($archivos) > self::MAX_ARCHIVOS) {
            return [[], "Puedes adjuntar máximo " . self::MAX_ARCHIVOS . " archivos a la vez."];
        }

        return [$archivos, null];
    }

    /*
     * Tipo real del archivo según su contenido.
     */
    private static function detectarTipo($ruta)
    {
        if (function_exists("finfo_open")) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $tipo = finfo_file($finfo, $ruta);
            finfo_close($finfo);

            return $tipo;
        }

        // Respaldo si la extensión fileinfo no está habilitada.
        $imagen = @getimagesize($ruta);

        if ($imagen) {
            return $imagen["mime"];
        }

        return file_get_contents($ruta, false, null, 0, 5) === "%PDF-" ? "application/pdf" : "";
    }

    private static function limpiarNombre($nombre)
    {
        $nombre = basename(str_replace("\\", "/", (string) $nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', "", $nombre);

        return mb_substr($nombre !== "" ? $nombre : "archivo", 0, 200);
    }

    /*
     * Mueve los archivos validados a storage/ y los registra.
     * Debe llamarse dentro de la transacción de la acción.
     */
    public function guardar($incidencia_id, $comentario_id, $usuario_id, array $archivos)
    {
        if (!$archivos) {
            return;
        }

        $directorio = self::directorio();

        if (!is_dir($directorio) && !mkdir($directorio, 0775, true)) {
            throw new RuntimeException("No existe la carpeta de evidencias.");
        }

        $stmt = $this->conn->prepare("
            INSERT INTO evidencias
            (incidencia_id, comentario_id, usuario_id, nombre_original, archivo, tipo_mime, tamano)
            VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($archivos as $archivo) {

            $destino = bin2hex(random_bytes(16)) . "." . self::TIPOS[$archivo["tipo"]];

            if (!move_uploaded_file($archivo["tmp"], $directorio . "/" . $destino)) {
                throw new RuntimeException("No se pudo guardar «" . $archivo["nombre"] . "».");
            }

            $this->movidos[] = $destino;

            $stmt->execute([
                $incidencia_id,
                $comentario_id,
                $usuario_id,
                $archivo["nombre"],
                $destino,
                $archivo["tipo"],
                $archivo["tamano"]
            ]);
        }
    }

    /*
     * Borra los archivos movidos si la transacción se revirtió.
     */
    public function deshacer()
    {
        foreach ($this->movidos as $archivo) {
            @unlink(self::ruta($archivo));
        }

        $this->movidos = [];
    }

    public function buscarPorId($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM evidencias WHERE id = ?");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * Evidencias de una incidencia agrupadas:
     *   ["inicial" => [...], "comentarios" => [comentario_id => [...]]]
     */
    public function listarPorIncidencia($incidencia_id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM evidencias
            WHERE incidencia_id = ?
            ORDER BY id
        ");

        $stmt->execute([$incidencia_id]);

        $agrupadas = ["inicial" => [], "comentarios" => []];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $evidencia) {

            if ($evidencia["comentario_id"] === null) {
                $agrupadas["inicial"][] = $evidencia;
            } else {
                $agrupadas["comentarios"][$evidencia["comentario_id"]][] = $evidencia;
            }
        }

        return $agrupadas;
    }

    /*
     * Elimina el registro; el archivo se borra con borrarArchivo()
     * después del commit.
     */
    public function eliminar($evidencia)
    {
        $this->conn->prepare("DELETE FROM evidencias WHERE id = ?")->execute([$evidencia["id"]]);
    }

    public static function borrarArchivo($archivo)
    {
        @unlink(self::ruta($archivo));
    }

    public static function esImagen($evidencia)
    {
        return strpos($evidencia["tipo_mime"], "image/") === 0;
    }

    public static function formatearTamano($bytes)
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . " MB";
        }

        return max(1, round($bytes / 1024)) . " KB";
    }
}
