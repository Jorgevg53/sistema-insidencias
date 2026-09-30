<?php

$tituloPagina = "Iniciar sesión";
$subtituloAcceso = "Ingresa con tu cuenta institucional para continuar.";
$enlaceRegreso = false;

require __DIR__ . "/tarjeta_inicio.php";

?>

<?php if (!empty($error)): ?>

    <div class="error" role="alert">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<form method="POST">

    <div>
        <label for="correo">Correo electrónico</label>
        <div class="campo-icono">
            <?= icono("correo") ?>
            <input
                type="email"
                id="correo"
                name="correo"
                value="<?= e($_POST["correo"] ?? "") ?>"
                placeholder="usuario@teschi.edu.mx"
                autocomplete="username"
                required
                autofocus
            >
        </div>
    </div>

    <div>
        <div class="etiqueta-fila">
            <label for="password">Contraseña</label>
            <a href="recuperar.php">¿La olvidaste?</a>
        </div>
        <div class="campo-icono">
            <?= icono("candado") ?>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >
            <button
                type="button"
                class="campo-ver"
                data-ver-password="#password"
                aria-label="Mostrar contraseña"
                title="Mostrar contraseña"
            >
                <?= icono("ojo", "icono ver-mostrar") ?>
                <?= icono("ojo_no", "icono ver-ocultar") ?>
            </button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">
        Iniciar sesión <?= icono("flecha_der") ?>
    </button>

</form>

<?php require __DIR__ . "/tarjeta_fin.php"; ?>
