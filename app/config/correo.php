<?php

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE CORREO (OPCIONAL)
|--------------------------------------------------------------------------
| Con "habilitado" => false (predeterminado) la recuperación de contraseña
| funciona sin correo: el Administrador recibe un aviso y genera el enlace.
|
| Para enviar el enlace por correo NO edites este archivo: crea
| app/config/correo.local.php (no se sube a git) con solo lo que cambia:
|
|   <?php
|   return [
|       "habilitado" => true,
|       "usuario"    => "tu_cuenta@gmail.com",
|       "password"   => "xxxx xxxx xxxx xxxx",   // "Contraseña de aplicación" de Google
|       "remitente"  => "tu_cuenta@gmail.com",
|       "url_base"   => "http://localhost/sistema-incidencias/public",
|   ];
|
| Gmail: activa la verificación en 2 pasos y crea una contraseña de
| aplicación en https://myaccount.google.com/apppasswords
|
| XAMPP en Windows: si falla con un error de certificado, agrega en php.ini
|   openssl.cafile = "C:\xampp\apache\bin\curl-ca-bundle.crt"
| y reinicia Apache.
*/

$configuracion = [
    "habilitado" => false,

    "host" => "smtp.gmail.com",
    "puerto" => 587,
    "seguridad" => "tls",        // "tls" (587), "ssl" (465) o "" (sin cifrado, solo pruebas)
    "usuario" => "",
    "password" => "",

    "remitente" => "",
    "nombre_remitente" => "Sistema de Incidencias TESCHI",

    /*
     * Dirección con la que se arman los enlaces de los correos, hasta la
     * carpeta public/. Es obligatoria: no se toma del navegador para
     * evitar que alguien falsifique el dominio del enlace.
     */
    "url_base" => "",
];

$local = __DIR__ . "/correo.local.php";

if (is_file($local)) {
    $configuracion = array_merge($configuracion, require $local);
}

return $configuracion;
