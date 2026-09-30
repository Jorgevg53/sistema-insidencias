<?php
/*
 * Cierre de las pantallas sin sesión.
 * Variables: $enlaceRegreso (opcional, true por defecto)
 */
?>
            <?php if ($enlaceRegreso ?? true): ?>
                <a href="login.php" class="acceso-regresar"><?= icono("flecha_izq") ?> Regresar al inicio de sesión</a>
            <?php endif; ?>

        </div>

        <p class="acceso-pie">
            Tecnológico de Estudios Superiores de Chimalhuacán · Departamento de Ciencias Básicas
        </p>

    </section>

</div>

<script src="js/app.js"></script>

</body>

</html>
