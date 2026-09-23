<?php

require_once "../app/helpers/auth.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
    exit;
}

require_once "../app/controllers/AuthController.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim(
        $_POST["correo"] ?? ""
    );

    $password =
        $_POST["password"] ?? "";

    if (
        empty($correo) ||
        empty($password)
    ) {

        $error =
            "Todos los campos son obligatorios.";

    } else {

        $auth =
            new AuthController();

        $resultado =
            $auth->login(
                $correo,
                $password
            );

        if ($resultado["success"]) {

            header(
                "Location: dashboard.php"
            );

            exit;

        } else {

            $error =
                $resultado["message"];
        }
    }
}

require_once "../app/views/auth/login.php";