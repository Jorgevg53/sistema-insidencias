<?php

/*
|--------------------------------------------------------------------------
| EVIDENCIAS (arreglo "evidencias" embebido en cada incidencia)
|--------------------------------------------------------------------------
| - Los archivos se guardan en storage/evidencias/ (fuera de public/) con un
|   nombre aleatorio; nunca con el nombre que envió el usuario.
| - El tipo se detecta por el CONTENIDO del archivo, no por la extensión.
| - Los metadatos van embebidos en el documento de la incidencia.
| - Solo se descargan mediante public/evidencia.php, que revisa permisos.
*/

class Evidencia
{
    const MAX_ARCHIVOS = 5;
    const MAX_BYTES = 5 * 1024 * 1024; // 5 MB por archivo

    const TIPOS = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "application/pdf" => "pdf",
    ];

    private $db;
    private $col;

    private $movidos = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->col = $db->getCollection("incidencias");
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
                "tamano" => (int) $subidos["size"][$i],
            ];
        }

        if (count($archivos) > self::MAX_ARCHIVOS) {
            return [[], "Puedes adjuntar máximo " . self::MAX_ARCHIVOS . " archivos a la vez."];
        }

        return [$archivos, null];
    }

    private static function detectarTipo($ruta)
    {
        if (function_exists("finfo_open")) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $tipo = finfo_file($finfo, $ruta);
            finfo_close($finfo);

            return $tipo;
        }

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
     * Mueve los archivos validados a storage/ y los embebe en la incidencia.
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

        foreach ($archivos as $archivo) {

            $destino = bin2hex(random_bytes(16)) . "." . self::TIPOS[$archivo["tipo"]];

            if (!move_uploaded_file($archivo["tmp"], $directorio . "/" . $destino)) {
                throw new RuntimeException("No se pudo guardar «" . $archivo["nombre"] . "».");
            }

            $this->movidos[] = $destino;

            $this->col->updateOne(
                ["_id" => (int) $incidencia_id],
                ['$push' => ["evidencias" => [
                    "id" => Database::siguienteId($this->db, "evidencias"),
                    "comentario_id" => $comentario_id !== null ? (int) $comentario_id : null,
                    "usuario_id" => (int) $usuario_id,
                    "nombre_original" => $archivo["nombre"],
                    "archivo" => $destino,
                    "tipo_mime" => $archivo["tipo"],
                    "tamano" => (int) $archivo["tamano"],
                    "fecha" => Database::ahora(),
                ]]]
            );
        }
    }

    /*
     * Borra los archivos movidos si algo falló después.
     */
    public function deshacer()
    {
        foreach ($this->movidos as $archivo) {
            @unlink(self::ruta($archivo));
        }

        $this->movidos = [];
    }

    /*
     * Busca una evidencia por su id dentro de todas las incidencias.
     * Devuelve el subdocumento con "incidencia_id" añadido, o false.
     */
    public function buscarPorId($id)
    {
        $doc = $this->col->findOne(
            ["evidencias.id" => (int) $id],
            ["projection" => ["evidencias" => 1]]
        );

        if (!$doc) {
            return false;
        }

        foreach ($doc["evidencias"] as $evidencia) {
            if ((int) $evidencia["id"] === (int) $id) {
                $evidencia["incidencia_id"] = (int) $doc["_id"];
                return $evidencia;
            }
        }

        return false;
    }

    /*
     * Evidencias de una incidencia agrupadas:
     *   ["inicial" => [...], "comentarios" => [comentario_id => [...]]]
     */
    public function listarPorIncidencia($incidencia_id)
    {
        $doc = $this->col->findOne(
            ["_id" => (int) $incidencia_id],
            ["projection" => ["evidencias" => 1]]
        );

        $agrupadas = ["inicial" => [], "comentarios" => []];

        if (!$doc || empty($doc["evidencias"])) {
            return $agrupadas;
        }

        $evidencias = $doc["evidencias"];

        // Se conserva el orden de inserción (por id).
        usort($evidencias, fn($a, $b) => (int) $a["id"] <=> (int) $b["id"]);

        foreach ($evidencias as $evidencia) {

            if (($evidencia["comentario_id"] ?? null) === null) {
                $agrupadas["inicial"][] = $evidencia;
            } else {
                $agrupadas["comentarios"][(int) $evidencia["comentario_id"]][] = $evidencia;
            }
        }

        return $agrupadas;
    }

    /*
     * Quita la evidencia del documento; el archivo se borra con
     * borrarArchivo() después.
     */
    public function eliminar($evidencia)
    {
        $this->col->updateOne(
            ["_id" => (int) $evidencia["incidencia_id"]],
            ['$pull' => ["evidencias" => ["id" => (int) $evidencia["id"]]]]
        );
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
