<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Usuario.php";

class AuthController
{
    private $conn;
    private $usuarioModel;

    public function __construct()
    {
        $database = new Database();

        $this->conn = $database->conectar();

        $this->usuarioModel = new Usuario($this->conn);
    }

    public function login($correo, $password)
    {
        $usuario = $this->usuarioModel->buscarPorCorreo($correo);

        if (!$usuario) {

            return [
                "success" => false,
                "message" => "El usuario no existe."
            ];
        }

        if (!password_verify($password, $usuario["password"])) {

            return [
                "success" => false,
                "message" => "La contraseña es incorrecta."
            ];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        /*
         * Nuevo identificador de sesión al iniciar sesión
         * (evita la fijación de sesión).
         */
        session_regenerate_id(true);

        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["apellido_paterno"] = $usuario["apellido_paterno"];
        $_SESSION["correo"] = $usuario["correo"];
        $_SESSION["rol_id"] = $usuario["rol_id"];
        $_SESSION["rol"] = $usuario["rol"];

        return [
            "success" => true,
            "message" => "Inicio de sesión correcto."
        ];
    }
}