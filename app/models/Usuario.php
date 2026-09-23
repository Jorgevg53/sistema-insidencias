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
}