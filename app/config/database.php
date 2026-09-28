<?php

/*
|--------------------------------------------------------------------------
| CONEXIÓN A MONGODB
|--------------------------------------------------------------------------
| El sistema usa MongoDB (base de datos de documentos, NoSQL). Se necesita:
|   - La extensión "mongodb" de PHP activada (extension=mongodb en php.ini).
|   - La librería mongodb/mongodb, ya incluida en vendor/ (no requiere Composer).
|   - Un servidor MongoDB en ejecución: local en el puerto 27017 o MongoDB Atlas.
|
| La cadena de conexión y el nombre de la base se cambian aquí o con las
| variables de entorno MONGODB_URI y MONGODB_DB (cómodo para MongoDB Atlas).
*/

require_once dirname(__DIR__, 2) . "/vendor/autoload.php";

class Database
{
    private $uri;
    private $db_name;

    public function __construct()
    {
        // Ejemplo Atlas: "mongodb+srv://usuario:clave@cluster0.xxxx.mongodb.net"
        $this->uri = getenv("MONGODB_URI") ?: "mongodb://127.0.0.1:27017";
        $this->db_name = getenv("MONGODB_DB") ?: "sistema_incidencias";
    }

    /*
     * Devuelve la base de datos de MongoDB lista para usar. Todas las
     * consultas devuelven arreglos de PHP (typeMap) para que el resto del
     * sistema funcione igual que con la versión anterior.
     */
    public function conectar()
    {
        if (!extension_loaded("mongodb")) {

            error_log("Falta la extensión «mongodb» de PHP.");

            die(
                "No se pudo conectar a la base de datos: falta la extensión «mongodb» de PHP. " .
                "Actívala en php.ini (agrega la línea: extension=mongodb) y reinicia Apache."
            );
        }

        try {

            $client = new MongoDB\Client($this->uri);

            $db = $client->getDatabase($this->db_name, [
                "typeMap" => [
                    "root" => "array",
                    "document" => "array",
                    "array" => "array",
                ],
            ]);

            // Comprueba la conexión aquí, no en la primera consulta.
            $db->command(["ping" => 1]);

            return $db;

        } catch (Exception $e) {

            error_log("Error de conexión a MongoDB: " . $e->getMessage());

            die(
                "No se pudo conectar a la base de datos. " .
                "Verifica que el servicio de MongoDB esté iniciado y revisa app/config/database.php."
            );
        }
    }

    /*
     * Identificador entero autoincremental por colección (equivale al
     * AUTO_INCREMENT de SQL). Usa la colección "contadores" de forma atómica.
     */
    public static function siguienteId($db, $coleccion)
    {
        $doc = $db->getCollection("contadores")->findOneAndUpdate(
            ["_id" => $coleccion],
            ['$inc' => ["seq" => 1]],
            [
                "upsert" => true,
                "returnDocument" => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        return (int) $doc["seq"];
    }

    /*
     * Fecha y hora actual con el formato que usa todo el sistema
     * (equivale al NOW() de SQL). Se guarda como texto "Y-m-d H:i:s"
     * para que las fechas se muestren y se comparen igual que antes.
     */
    public static function ahora()
    {
        return date("Y-m-d H:i:s");
    }
}
