<?php

require_once "../app/helpers/auth.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
    exit;
}

require_once "../app/controllers/AuthController.php";

$error = "";
$correo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");

    $password = $_POST["password"] ?? "";

    if (!verificarCsrf()) {

        $error = "La sesión del formulario expiró. Intenta de nuevo.";

    } elseif ($correo === "" || $password === "") {

        $error = "Todos los campos son obligatorios.";

    } else {

        $auth = new AuthController();

        $resultado = $auth->login($correo, $password);

        if ($resultado["success"]) {

            header("Location: dashboard.php");
            exit;

        }

        $error = $resultado["message"];
    }
}

require_once "../app/views/auth/login.php";
