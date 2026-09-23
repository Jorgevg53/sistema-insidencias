<?php

class Usuario
{
    private $conn;
    private $table = "usuarios";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function buscarPorCorreo($correo)
    {
        $sql = "
            SELECT
                u.id,
                u.matricula,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.correo,
                u.password,
                u.departamento,
                r.id AS rol_id,
                r.nombre AS rol
            FROM usuarios u

            INNER JOIN roles r
                ON u.rol_id = r.id

            WHERE u.correo = ?
            AND u.activo = 1

            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$correo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorId($id)
    {
        $sql = "
            SELECT
                u.*,
                r.nombre AS rol
            FROM usuarios u
            INNER JOIN roles r
                ON u.rol_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * Lista de usuarios con filtros opcionales:
     * texto (nombre, correo o matrícula), rol_id y activo (1/0).
     */
    public function listar($texto = "", $rol_id = "", $activo = "")
    {
        $condiciones = [];
        $parametros = [];

        if ($texto !== "") {
            $condiciones[] = "(
                CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) LIKE ?
                OR u.correo LIKE ?
                OR u.matricula LIKE ?
            )";
            $parametros[] = "%" . $texto . "%";
            $parametros[] = "%" . $texto . "%";
            $parametros[] = "%" . $texto . "%";
        }

        if ($rol_id !== "") {
            $condiciones[] = "u.rol_id = ?";
            $parametros[] = $rol_id;
        }

        if ($activo !== "") {
            $condiciones[] = "u.activo = ?";
            $parametros[] = $activo;
        }

        $sql = "
            SELECT
                u.id,
                u.matricula,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.correo,
                u.departamento,
                u.activo,
                u.fecha_registro,
                r.nombre AS rol
            FROM usuarios u
            INNER JOIN roles r
                ON u.rol_id = r.id
            " . ($condiciones ? "WHERE " . implode(" AND ", $condiciones) : "") . "
            ORDER BY u.nombre, u.apellido_paterno
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRoles()
    {
        return $this->conn->query("
            SELECT id, nombre
            FROM roles
            WHERE activo = 1
            ORDER BY id
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /*
     * Indica si un valor (correo o matrícula) ya está en uso
     * por otro usuario distinto de $excluir_id.
     */
    public function existe($campo, $valor, $excluir_id = 0)
    {
        if (!in_array($campo, ["correo", "matricula"], true)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM usuarios
            WHERE $campo = ?
            AND id <> ?
        ");

        $stmt->execute([$valor, $excluir_id]);

        return $stmt->fetchColumn() > 0;
    }

    public function crear($datos)
    {
        $sql = "
            INSERT INTO usuarios
            (
                matricula,
                nombre,
                apellido_paterno,
                apellido_materno,
                correo,
                password,
                rol_id,
                departamento,
                telefono
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            $datos["matricula"],
            $datos["nombre"],
            $datos["apellido_paterno"],
            $datos["apellido_materno"],
            $datos["correo"],
            password_hash($datos["password"], PASSWORD_DEFAULT),
            $datos["rol_id"],
            $datos["departamento"],
            $datos["telefono"]
        ]);

        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $datos)
    {
        $sql = "
            UPDATE usuarios
            SET
                matricula = ?,
                nombre = ?,
                apellido_paterno = ?,
                apellido_materno = ?,
                correo = ?,
                rol_id = ?,
                departamento = ?,
                telefono = ?
            WHERE id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            $datos["matricula"],
            $datos["nombre"],
            $datos["apellido_paterno"],
            $datos["apellido_materno"],
            $datos["correo"],
            $datos["rol_id"],
            $datos["departamento"],
            $datos["telefono"],
            $id
        ]);

        if (!empty($datos["password"])) {
            $this->cambiarPassword($id, $datos["password"]);
        }
    }

    public function cambiarPassword($id, $password)
    {
        $stmt = $this->conn->prepare("
            UPDATE usuarios
            SET password = ?
            WHERE id = ?
        ");

        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id
        ]);
    }

    public function cambiarActivo($id, $activo)
    {
        $stmt = $this->conn->prepare("
            UPDATE usuarios
            SET activo = ?
            WHERE id = ?
        ");

        $stmt->execute([$activo ? 1 : 0, $id]);
    }
}
