<?php 
 
require_once "../app/config/database.php"; 
 
$database = new Database(); 
 
$conn = $database->conectar(); 
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Sistema de Incidencias TESCHI</title>
</head>

<body>

    <h1>
        Sistema de Gestión de Incidencias
    </h1>

    <p>
        Departamento de Ciencias Básicas
    </p>

    <p>
        Tecnológico de Estudios Superiores de Chimalhuacán
    </p>

    <hr>

    <p>
        Conexión con la base de datos:
        <strong>Correcta</strong>
    </p>

</body>

</html>