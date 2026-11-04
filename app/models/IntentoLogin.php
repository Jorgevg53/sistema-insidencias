<?php

/*
|--------------------------------------------------------------------------
| INTENTOS FALLIDOS DE INICIO DE SESIÓN (colección "intentos_login")
|--------------------------------------------------------------------------
| Protección contra ataques de fuerza bruta y de diccionario. En una
| ventana de 15 minutos se bloquea el inicio de sesión cuando hay:
|   - 5 fallos para la misma cuenta desde la misma IP,
|   - 20 fallos desde la misma IP (contra cualquier cuenta), o
|   - 50 fallos para la misma cuenta desde cualquier IP (ataque repartido).
|
| Los límites por cuenta+IP y por IP no permiten que un tercero bloquee
| con facilidad la cuenta de otra persona; el límite global por cuenta es
| alto por la misma razón. Los registros se borran solos (índice TTL).
*/

class IntentoLogin
{
    const VENTANA_MINUTOS = 15;

    const MAX_CUENTA_IP = 5;
    const MAX_IP = 20;
    const MAX_CUENTA = 50;

    private $col;

    public function __construct($db)
    {
        $this->col = $db->getCollection("intentos_login");
    }

    public static function ipCliente()
    {
        return substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
    }

    private static function clave($correo)
    {
        return mb_substr(mb_strtolower(trim((string) $correo)), 0, 254);
    }

    private static function desde()
    {
        return new MongoDB\BSON\UTCDateTime((time() - self::VENTANA_MINUTOS * 60) * 1000);
    }

    /*
     * true si ya no se permite intentar (para esa cuenta o esa IP).
     */
    public function bloqueado($correo, $ip)
    {
        $desde = ['$gte' => self::desde()];
        $correo = self::clave($correo);
        $ip = (string) $ip;

        return $this->col->countDocuments(["correo" => $correo, "ip" => $ip, "fecha" => $desde]) >= self::MAX_CUENTA_IP
            || $this->col->countDocuments(["ip" => $ip, "fecha" => $desde]) >= self::MAX_IP
            || $this->col->countDocuments(["correo" => $correo, "fecha" => $desde]) >= self::MAX_CUENTA;
    }

    public function registrarFallo($correo, $ip)
    {
        $this->col->insertOne([
            "correo" => self::clave($correo),
            "ip" => (string) $ip,
            "fecha" => new MongoDB\BSON\UTCDateTime(),
        ]);

        // Borrado automático de los registros viejos (la operación es idempotente).
        try {
            $this->col->createIndex(["fecha" => 1], ["expireAfterSeconds" => 24 * 60 * 60]);
        } catch (Exception $e) {
            error_log("No se pudo crear el índice TTL de intentos_login: " . $e->getMessage());
        }
    }

    /*
     * Tras un inicio de sesión correcto se olvidan los fallos de esa cuenta
     * desde esa IP.
     */
    public function limpiar($correo, $ip)
    {
        $this->col->deleteMany(["correo" => self::clave($correo), "ip" => (string) $ip]);
    }
}
