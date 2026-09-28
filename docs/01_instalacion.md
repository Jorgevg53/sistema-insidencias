# Guía de instalación

Sistema de Gestión de Incidencias · Departamento de Ciencias Básicas · TESCHI

## 1. Requisitos

| Componente | Versión mínima | Notas |
|---|---|---|
| XAMPP (Apache + PHP) o hosting | PHP 8.1 o superior | Probado con PHP 8.4 y Apache 2.4 |
| **MongoDB** | 5.0 o superior | MongoDB Community Server (local) o MongoDB Atlas (nube) |
| Extensión **`mongodb`** de PHP | 1.15+ | **No viene en XAMPP**; se instala aparte (ver 1.1) |
| Otras extensiones de PHP | `mbstring`, `fileinfo`, `iconv` | Vienen activas en XAMPP |
| Extensión `openssl` | — | Solo si se configura el envío de correos |
| Navegador | Chrome, Edge o Firefox actuales | También funciona en celular |

No se necesita Composer: FPDF (ticket en PDF) y la librería `mongodb/mongodb` ya vienen incluidas
(`app/lib/fpdf/` y `vendor/`).

### 1.1 Instalar la extensión `mongodb` de PHP (en XAMPP)

1. Averigua tu versión de PHP y si es *Thread Safe*: en <http://localhost/dashboard/phpinfo.php>
   busca **PHP Version** y **Thread Safety** y **Architecture** (x64).
2. Descarga el paquete de <https://pecl.php.net/package/mongodb> (DLL Windows) que coincida
   con tu versión de PHP, TS/NTS y x64.
3. Copia `php_mongodb.dll` en `C:\xampp\php\ext`.
4. En `C:\xampp\php\php.ini` agrega una línea:

   ```ini
   extension=mongodb
   ```

5. Reinicia **Apache** desde el Panel de control de XAMPP.
6. Comprueba en phpinfo que aparece una sección **mongodb**.

> En Linux/Mac: `sudo pecl install mongodb` y agrega `extension=mongodb.so` al `php.ini`.

### 1.2 Instalar el servidor MongoDB

- **Local:** descarga **MongoDB Community Server** de <https://www.mongodb.com/try/download/community>,
  instálalo y deja que se ejecute como servicio (escucha en `mongodb://127.0.0.1:27017`).
- **En la nube:** crea un clúster gratuito en **MongoDB Atlas** y copia su cadena de conexión
  (`mongodb+srv://...`).

## 2. Instalación nueva

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\sistema-incidencias`.
2. Abre el **Panel de control de XAMPP** e inicia **Apache**. Asegúrate de que el servicio de
   **MongoDB** esté corriendo (sección 1.2).
3. Revisa la conexión en `app/config/database.php`:
   - Por defecto usa `mongodb://127.0.0.1:27017` y la base `sistema_incidencias`.
   - Para MongoDB Atlas, define las variables de entorno `MONGODB_URI` y `MONGODB_DB`, o edita esos
     valores en el archivo.
4. Crea las colecciones, los índices y los datos iniciales ejecutando el inicializador en una terminal:

   ```
   C:\xampp\php\php.exe database\seed_mongo.php
   ```

   (En Linux/Mac: `php database/seed_mongo.php`.) Verás las cuentas creadas al final.
5. Abre `http://localhost/sistema-incidencias/` (te envía a `public/`) e inicia sesión con la cuenta del Administrador
   (`admin@teschi.edu.mx` / `Admin1234`). Cámbiala en **Mi perfil**.
6. En **Usuarios** crea las cuentas de coordinadores, docentes, personal administrativo y estudiantes.

> El inicializador crea también algunos usuarios y unas incidencias de ejemplo para que puedas
> probar de inmediato. No borra nada si ya hay datos; para reiniciarlo usa `... seed_mongo.php force`.

## 3. Cómo se guardan los datos

MongoDB no usa tablas sino **colecciones de documentos**. Las principales son `usuarios`,
`incidencias`, `notificaciones`, `restablecimientos`, y los catálogos `roles`, `estados`,
`categorias` y `prioridades`. Cada incidencia **embebe** su historial, sus comentarios y sus
evidencias dentro del mismo documento. La colección `contadores` da los identificadores numéricos
(como el AUTO_INCREMENT de SQL). El diccionario completo está en la
[documentación de la base de datos](03_base_de_datos.md).

Para poner el sistema en producción (red local u hosting) sigue la [Guía de despliegue paso a paso](05_despliegue.md).

## 4. Configuración

### 4.1 Archivos adjuntos (evidencias)

- Los archivos se guardan en `storage/evidencias/`. La carpeta debe poder escribirse (en Windows no hay que hacer nada).
- En `C:\xampp\php\php.ini` verifica que:

  ```ini
  upload_max_filesize = 40M
  post_max_size = 40M
  ```

  (XAMPP ya trae esos valores; el sistema permite 5 archivos de 5 MB por envío).
- `storage/` y `database/` tienen un archivo `.htaccess` que impide abrirlas desde el navegador. No lo borres.

### 4.2 Datos de la institución y ticket en PDF

Edita `app/config/institucion.php` para cambiar:

- Nombre, dirección y departamento que aparecen en el ticket.
- La lista oficial de **carreras**.
- Las leyendas ("Conserve este ticket para garantía", firmas, "Gracias por su visita").

El encabezado del ticket lleva el logo del **Gobierno del Estado de México** a la izquierda
(`public/img/logo_edomex.png`) y el del **TESCHI** a la derecha (`public/img/logo_teschi.png`). Para cambiarlos,
reemplaza esos archivos (PNG o JPG; FPDF no lee WebP) o ajusta `logo_izquierdo` / `logo_derecho` en
`app/config/institucion.php` (una ruta vacía `""` quita ese logo).

### 4.3 Apariencia (colores y foto del login)

- La pantalla de inicio de sesión usa de fondo la foto del edificio (`public/img/fondo_login.jpg`); para cambiarla,
  reemplaza ese archivo por otra imagen JPG del mismo nombre.
- Los colores institucionales (verde TESCHI, verde limón, azul y rojo del logotipo) están al inicio de
  `public/css/style.css`, en el bloque `:root`; al cambiar una variable cambia en todo el sistema.
- El logotipo del encabezado y del login es `public/img/logo_teschi_web.png` (versión ligera del que usa el ticket).

### 4.4 Envío de correos (opcional)

Sin configurar nada, la recuperación de contraseña funciona **a través del Administrador** (él genera el enlace).
Para que el enlace llegue por correo:

1. En la cuenta de Gmail que enviará los correos, activa la verificación en dos pasos y crea una
   **contraseña de aplicación** en <https://myaccount.google.com/apppasswords>.
2. Crea el archivo `app/config/correo.local.php` (no se sube a GitHub):

   ```php
   <?php
   return [
       "habilitado" => true,
       "usuario"    => "cuenta@gmail.com",
       "password"   => "xxxx xxxx xxxx xxxx",
       "remitente"  => "cuenta@gmail.com",
       "url_base"   => "http://localhost/sistema-incidencias/public",
   ];
   ```

3. Si aparece un error de certificado, agrega en `php.ini` y reinicia Apache:

   ```ini
   openssl.cafile = "C:\xampp\apache\bin\curl-ca-bundle.crt"
   ```

## 5. Respaldos

Respalda **ambas** cosas:

1. La base de datos, con la herramienta `mongodump` (viene con MongoDB):

   ```
   mongodump --db sistema_incidencias --out C:\respaldos\incidencias
   ```

   Para restaurar: `mongorestore --db sistema_incidencias C:\respaldos\incidencias\sistema_incidencias`.
2. La carpeta `storage/evidencias/` (fotos y PDF adjuntos por los usuarios).

## 6. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| "falta la extensión «mongodb» de PHP" | La extensión no está activada | Sigue la sección 1.1 (copia el `.dll`, agrega `extension=mongodb` a `php.ini` y reinicia Apache) |
| "No se pudo conectar a la base de datos" | El servicio de MongoDB está apagado o la URI es incorrecta | Inicia MongoDB (sección 1.2) y revisa `app/config/database.php`. El motivo exacto queda en `C:\xampp\apache\logs\error.log` |
| Al iniciar sesión no reconoce ninguna cuenta | No se ejecutó el inicializador | Corre `php database\seed_mongo.php` (sección 2, punto 4) |
| No se pueden adjuntar archivos | Límite de `php.ini` | Sube `upload_max_filesize` y `post_max_size` |
| "Los archivos enviados superan el límite del servidor" | El envío total supera `post_max_size` | Adjunta menos archivos o más ligeros |
| Acentos raros en el ticket PDF | Extensión `iconv`/`mbstring` desactivada | Actívalas en `php.ini` |
| No llegan los correos | Correo sin configurar o bloqueado | Revisa la sección 4.4; mientras tanto el Administrador puede generar el enlace |
| La página muestra código PHP | Se abrió el archivo directamente | Usa `http://localhost/...`, no `file:///...` |
