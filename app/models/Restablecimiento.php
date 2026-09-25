<?php

/*
|--------------------------------------------------------------------------
| RESTABLECIMIENTO DE CONTRASEÑA
|--------------------------------------------------------------------------
| - El token (32 bytes aleatorios) solo viaja en el enlace; en la base
|   de datos se guarda su hash SHA-256.
| - Cada enlace sirve una sola vez y caduca.
| - Al generar uno nuevo, los anteriores del usuario se anulan.
| - Hay límite de solicitudes por correo y por IP para evitar abusos.
*/

class Restablecimiento
{
    const MINUTOS_CORREO = 60;             // Enlace enviado por correo
    const MINUTOS_ADMINISTRADOR = 24 * 60; // Enlace generado por el Administrador

    const LIMITE_POR_CORREO = 3; // solicitudes por hora
    const LIMITE_POR_IP = 10;    // solicitudes por hora

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public static function ipCliente()
    {
        return substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
    }

    /*
     * true si ese correo o esa IP ya hicieron demasiadas solicitudes
     * en la última hora.
     */
    public function limiteExcedido($correo, $ip)
    {
        $stmt = $this->conn->prepare("
            SELECT
                SUM(correo = ?) AS por_correo,
                SUM(ip = ?) AS por_ip
            FROM restablecimientos_password
            WHERE origen IN ('solicitud', 'correo')
            AND fecha > NOW() - INTERVAL 1 HOUR
        ");

        $stmt->execute([$correo, $ip]);

        $conteo = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $conteo["por_correo"] >= self::LIMITE_POR_CORREO
            || (int) $conteo["por_ip"] >= self::LIMITE_POR_IP;
    }

    /*
     * Deja constancia de una solicitud sin enlace (modo sin correo o
     * correo no registrado).
     */
    public function registrarSolicitud($usuario_id, $correo, $ip)
    {
        $this->conn->prepare("
            INSERT INTO restablecimientos_password
            (usuario_id, correo, origen, ip)
            VALUES
            (?, ?, 'solicitud', ?)
        ")->execute([$usuario_id, mb_substr($correo, 0, 150), $ip]);
    }

    /*
     * Crea un enlace nuevo, anula los anteriores y devuelve el token
     * en texto plano (es la única vez que existe así).
     */
    public function crearToken($usuario, $origen, $minutos, $creado_por = null, $ip = null)
    {
        $this->anularPendientes($usuario["id"]);

        $token = bin2hex(random_bytes(32));

        $this->conn->prepare("
            INSERT INTO restablecimientos_password
            (usuario_id, correo, origen, token_hash, creado_por, expira, ip)
            VALUES
            (?, ?, ?, ?, ?, NOW() + INTERVAL ? MINUTE, ?)
        ")->execute([
            $usuario["id"],
            $usuario["correo"],
            $origen,
            hash("sha256", $token),
            $creado_por,
            (int) $minutos,
            $ip
        ]);

        return $token;
    }

    /*
     * Enlace vigente para el token, con los datos del usuario.
     * Devuelve false si no existe, ya se usó, caducó o el usuario
     * está desactivado.
     */
    public function buscarVigente($token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT
                rp.id,
                rp.usuario_id,
                rp.expira,
                u.nombre,
                u.correo
            FROM restablecimientos_password rp
            INNER JOIN usuarios u
                ON rp.usuario_id = u.id
            WHERE rp.token_hash = ?
            AND rp.usado_en IS NULL
            AND rp.expira > NOW()
            AND u.activo = 1
            LIMIT 1
        ");

        $stmt->execute([hash("sha256", $token)]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * Cambia la contraseña y deja el enlace (y cualquier otro pendiente)
     * sin validez. Devuelve false si el enlace se usó mientras tanto
     * (por ejemplo, dos envíos simultáneos).
     */
    public function usar($restablecimiento, $password)
    {
        $marcar = $this->conn->prepare("
            UPDATE restablecimientos_password
            SET usado_en = NOW()
            WHERE id = ?
            AND usado_en IS NULL
            AND expira > NOW()
        ");

        $marcar->execute([$restablecimiento["id"]]);

        if ($marcar->rowCount() !== 1) {
            return false;
        }

        $this->conn->prepare("
            UPDATE usuarios
            SET password = ?
            WHERE id = ?
        ")->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $restablecimiento["usuario_id"]
        ]);

        $this->anularPendientes($restablecimiento["usuario_id"]);

        return true;
    }

    public function anularPendientes($usuario_id)
    {
        $this->conn->prepare("
            UPDATE restablecimientos_password
            SET usado_en = NOW()
            WHERE usuario_id = ?
            AND token_hash IS NOT NULL
            AND usado_en IS NULL
        ")->execute([$usuario_id]);
    }

    /*
     * Fecha de la última solicitud del usuario que sigue pendiente, es
     * decir, posterior al último enlace generado (para avisar al Administrador).
     */
    public function solicitudPendiente($usuario_id)
    {
        $stmt = $this->conn->prepare("
            SELECT MAX(s.fecha)
            FROM restablecimientos_password s
            WHERE s.usuario_id = ?
            AND s.origen = 'solicitud'
            AND s.fecha > COALESCE((
                SELECT MAX(t.fecha)
                FROM restablecimientos_password t
                WHERE t.usuario_id = s.usuario_id
                AND t.token_hash IS NOT NULL
            ), '1970-01-01')
        ");

        $stmt->execute([$usuario_id]);

        return $stmt->fetchColumn();
    }

    /*
     * Enlace completo a restablecer.php.
     * $urlBase debe apuntar a la carpeta public/ del sistema.
     */
    public static function enlace($urlBase, $token)
    {
        return rtrim($urlBase, "/") . "/restablecer.php?token=" . $token;
    }
}
