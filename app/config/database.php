<?php

class Database
{
    private $host = "localhost";
    private $db_name = "sistema_incidencias";
    private $username = "root";
    private $password = "";

    public $conn;

    public function conectar()
    {
        $this->conn = null;

        try {

            $this->conn = new PDO(
                "mysql:host=" . $this->host .
                ";dbname=" . $this->db_name .
                ";charset=utf8mb4",
                $this->username,
                $this->password
            );

            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

        } catch (PDOException $e) {

            /*
             * El detalle técnico va al log de Apache
             * (C:\xampp\apache\logs\error.log), no a la pantalla.
             */
            error_log("Error de conexión a la base de datos: " . $e->getMessage());

            die(
                "No se pudo conectar a la base de datos. " .
                "Verifica que MySQL esté iniciado en XAMPP y revisa app/config/database.php."
            );

        }

        return $this->conn;
    }
}