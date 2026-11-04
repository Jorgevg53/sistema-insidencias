<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Usuario.php";
require_once __DIR__ . "/../models/IntentoLogin.php";

class AuthController
{
    const MENSAJE_INCORRECTO = "Correo o contraseña incorrectos.";

    private $conn;
    private $usuarioModel;
    private $intentos;

    public function __construct()
    {
        $database = new Database();

        $this->conn = $database->conectar();

        $this->usuarioModel = new Usuario($this->conn);
        $this->intentos = new IntentoLogin($this->conn);
    }

    public function login($correo, $password)
    {
        if (!is_string($correo) || !is_string($password) || mb_strlen($correo) > 254 || strlen($password) > 4096) {
            return ["success" => false, "message" => self::MENSAJE_INCORRECTO];
        }

        $ip = IntentoLogin::ipCliente();

        /*
         * Límite de intentos: se revisa ANTES de comprobar la contraseña.
         */
        if ($this->intentos->bloqueado($correo, $ip)) {
            return [
                "success" => false,
                "message" => "Demasiados intentos fallidos. Espera " . IntentoLogin::VENTANA_MINUTOS
                    . " minutos e intenta de nuevo."
            ];
        }

        $usuario = $this->usuarioModel->buscarPorCorreo($correo);

        if (!$usuario) {
            /*
             * Se calcula un hash de todos modos para que la respuesta tarde
             * lo mismo exista o no la cuenta (evita descubrir correos
             * registrados midiendo el tiempo).
             */
            password_hash($password, PASSWORD_DEFAULT);

            $this->intentos->registrarFallo($correo, $ip);

            return ["success" => false, "message" => self::MENSAJE_INCORRECTO];
        }

        if (!password_verify($password, $usuario["password"])) {

            $this->intentos->registrarFallo($correo, $ip);

            return ["success" => false, "message" => self::MENSAJE_INCORRECTO];
        }

        $this->intentos->limpiar($correo, $ip);

        /*
         * Si el algoritmo o el costo del hash cambiaron, se actualiza.
         */
        if (password_needs_rehash($usuario["password"], PASSWORD_DEFAULT)) {
            $usuario["password"] = $this->usuarioModel->cambiarPassword($usuario["id"], $password, null);
        }

        /*
         * Contraseñas que ya no cumplen la política (p. ej. las de ejemplo
         * de la documentación): se obliga a cambiarlas.
         */
        if (passwordDebil($password, $usuario["correo"])) {
            $this->usuarioModel->marcarCambioPassword($usuario["id"], true);
        }

        iniciarSesionUsuario($usuario);

        return [
            "success" => true,
            "message" => "Inicio de sesión correcto."
        ];
    }
}
