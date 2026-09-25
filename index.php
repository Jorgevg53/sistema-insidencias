<?php

/*
 * Si se abre la carpeta del proyecto (http://servidor/sistema-incidencias/),
 * se envía a la aplicación, que vive en public/.
 * Así no se muestra la lista de archivos del proyecto.
 */

header("Location: public/");
exit;
