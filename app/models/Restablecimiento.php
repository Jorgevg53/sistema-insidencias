<?php

/*
|--------------------------------------------------------------------------
| RESTABLECIMIENTO DE CONTRASEÑA (colección "restablecimientos")
|--------------------------------------------------------------------------
| - El token (32 bytes aleatorios) solo viaja en el enlace; en la base de
|   datos se guarda su hash SHA-256.
| - Cada enlace sirve una sola vez y caduca.
| - Al generar uno nuevo, los anteriores del usuario se anulan.
| - Hay límite de solicitudes por correo y por IP para evitar abusos.
*/

class Restablecimiento
{
    const MINUTOS_CORREO = 60;
    const MINUTOS_ADMINISTRADOR = 24 * 60;

    const LIMITE_POR_CORREO = 3;
    const LIMITE_POR_IP = 10;

    private $db;
    private $col;

    public function __construct($db)
    {
        $this->db = $db;
        $this->col = $db->getCollection("restablecimientos");
    }

    public static function ipCliente()
    {
        return substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
    }

    /*
     * true si ese correo o esa IP ya hicieron demasiadas solicitudes en la
     * última hora.
     */
    public function limiteExcedido($correo, $ip)
    {
        $desde = date("Y-m-d H:i:s", strtotime("-1 hour"));

        $base = [
            "origen" => ['$in' => ["solicitud", "correo"]],
            "fecha" => ['$gt' => $desde],
        ];

        $porCorreo = $this->col->countDocuments($base + ["correo" => $correo]);
        $porIp = $this->col->countDocuments($base + ["ip" => $ip]);

        return $porCorreo >= self::LIMITE_POR_CORREO || $porIp >= self::LIMITE_POR_IP;
    }

    /*
     * Deja constancia de una solicitud sin enlace (modo sin correo o correo
     * no registrado).
     */
    public function registrarSolicitud($usuario_id, $correo, $ip)
    {
        $this->col->insertOne([
            "_id" => Database::siguienteId($this->db, "restablecimientos"),
            "usuario_id" => $usuario_id !== null ? (int) $usuario_id : null,
            "correo" => mb_substr((string) $correo, 0, 150),
            "origen" => "solicitud",
            "token_hash" => null,
            "creado_por" => null,
            "ip" => $ip,
            "fecha" => Database::ahora(),
            "expira" => null,
            "usado_en" => null,
        ]);
    }

    /*
     * Crea un enlace nuevo, anula los anteriores y devuelve el token en texto
     * plano (es la única vez que existe así).
     */
    public function crearToken($usuario, $origen, $minutos, $creado_por = null, $ip = null)
    {
        $this->anularPendientes($usuario["id"]);

        $token = bin2hex(random_bytes(32));

        $this->col->insertOne([
            "_id" => Database::siguienteId($this->db, "restablecimientos"),
            "usuario_id" => (int) $usuario["id"],
            "correo" => $usuario["correo"],
            "origen" => $origen,
            "token_hash" => hash("sha256", $token),
            "creado_por" => $creado_por !== null ? (int) $creado_por : null,
            "ip" => $ip,
            "fecha" => Database::ahora(),
            "expira" => date("Y-m-d H:i:s", strtotime("+" . (int) $minutos . " minutes")),
            "usado_en" => null,
        ]);

        return $token;
    }

    /*
     * Enlace vigente para el token, con los datos del usuario. Devuelve false
     * si no existe, ya se usó, caducó o el usuario está desactivado.
     */
    public function buscarVigente($token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $doc = $this->col->findOne([
            "token_hash" => hash("sha256", $token),
            "usado_en" => null,
            "expira" => ['$gt' => Database::ahora()],
        ]);

        if (!$doc) {
            return false;
        }

        $usuario = $this->db->getCollection("usuarios")->findOne(
            ["_id" => (int) $doc["usuario_id"], "activo" => true],
            ["projection" => ["nombre" => 1, "correo" => 1]]
        );

        if (!$usuario) {
            return false;
        }

        return [
            "id" => (int) $doc["_id"],
            "usuario_id" => (int) $doc["usuario_id"],
            "expira" => $doc["expira"],
            "nombre" => $usuario["nombre"],
            "correo" => $usuario["correo"],
        ];
    }

    /*
     * Cambia la contraseña y deja el enlace (y los demás pendientes) sin
     * validez. Devuelve false si el enlace se usó mientras tanto.
     */
    public function usar($restablecimiento, $password)
    {
        $resultado = $this->col->updateOne(
            [
                "_id" => (int) $restablecimiento["id"],
                "usado_en" => null,
                "expira" => ['$gt' => Database::ahora()],
            ],
            ['$set' => ["usado_en" => Database::ahora()]]
        );

        if ($resultado->getModifiedCount() !== 1) {
            return false;
        }

        $this->db->getCollection("usuarios")->updateOne(
            ["_id" => (int) $restablecimiento["usuario_id"]],
            ['$set' => ["password" => password_hash($password, PASSWORD_DEFAULT)]]
        );

        $this->anularPendientes($restablecimiento["usuario_id"]);

        return true;
    }

    public function anularPendientes($usuario_id)
    {
        $this->col->updateMany(
            [
                "usuario_id" => (int) $usuario_id,
                "token_hash" => ['$ne' => null],
                "usado_en" => null,
            ],
            ['$set' => ["usado_en" => Database::ahora()]]
        );
    }

    /*
     * Fecha de la última solicitud del usuario posterior al último enlace
     * generado (para avisar al Administrador). Devuelve la fecha o false.
     */
    public function solicitudPendiente($usuario_id)
    {
        $ultimoToken = $this->col->findOne(
            ["usuario_id" => (int) $usuario_id, "token_hash" => ['$ne' => null]],
            ["sort" => ["fecha" => -1], "projection" => ["fecha" => 1]]
        );

        $corte = $ultimoToken["fecha"] ?? "1970-01-01 00:00:00";

        $solicitud = $this->col->findOne(
            [
                "usuario_id" => (int) $usuario_id,
                "origen" => "solicitud",
                "fecha" => ['$gt' => $corte],
            ],
            ["sort" => ["fecha" => -1], "projection" => ["fecha" => 1]]
        );

        return $solicitud ? $solicitud["fecha"] : false;
    }

    /*
     * Enlace completo a restablecer.php. $urlBase apunta a la carpeta public/.
     */
    public static function enlace($urlBase, $token)
    {
        return rtrim($urlBase, "/") . "/restablecer.php?token=" . $token;
    }
}
