<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Iniciar sesión | TESCHI
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>
            TESCHI
        </h1>

        <h2>
            Iniciar sesión
        </h2>

        <p>
            Sistema de Gestión de Incidencias
        </p>


        <?php $flash = obtenerFlash(); ?>

        <?php if ($flash): ?>

            <div class="alerta alerta-<?= e($flash["tipo"]) ?>">

                <?= e($flash["mensaje"]) ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div>

                <label for="correo">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="correo"
                    name="correo"
                    required
                >

            </div>


            <div>

                <label for="password">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Iniciar sesión
            </button>

        </form>


        <a href="recuperar.php">
            ¿Olvidaste tu contraseña?
        </a>



    </div>

</div>

</body>

</html>