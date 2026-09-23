# Guía de instalación

Sistema de Gestión de Incidencias · Departamento de Ciencias Básicas · TESCHI

## 1. Requisitos

| Componente | Versión mínima | Notas |
|---|---|---|
| XAMPP (Apache + MariaDB + PHP) | PHP 8.0 o superior, MariaDB 10.4 o superior | Probado con PHP 8.2/8.4 y MariaDB 10.4/10.11 |
| Extensiones de PHP | `pdo_mysql`, `mbstring`, `fileinfo`, `iconv` | Vienen activas en XAMPP |
| Extensión `openssl` | — | Solo si se configura el envío de correos |
| Navegador | Chrome, Edge o Firefox actuales | También funciona en celular |

No se necesita Composer ni instalar librerías: FPDF (para el ticket en PDF) ya viene incluida en `app/lib/fpdf/`.

## 2. Instalación nueva

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\sistema-incidencias`.
2. Abre el **Panel de control de XAMPP** e inicia **Apache** y **MySQL**.
3. Entra a `http://localhost/phpmyadmin`:
   1. Crea la base de datos **`sistema_incidencias`** con cotejamiento `utf8mb4_unicode_ci`.
   2. Selecciónala, abre la pestaña **Importar** y carga `database/database.sql`.
4. Revisa los datos de conexión en `app/config/database.php` (por defecto usuario `root` sin contraseña).
5. Abre `http://localhost/sistema-incidencias/public/` e inicia sesión con la cuenta del Administrador
   (`admin@teschi.edu.mx`).
6. En **Usuarios** crea las cuentas de coordinadores, docentes, personal administrativo y estudiantes.

> `database/database.sql` ya incluye todas las tablas y cambios. Las migraciones del punto 3 **no** son necesarias
> en una instalación nueva.

## 3. Actualizar una base de datos existente

Si ya tenías el sistema instalado con una versión anterior, ejecuta en phpMyAdmin (base `sistema_incidencias` →
pestaña **SQL**) los archivos de `database/migraciones/` que te falten, **en este orden**:

| Archivo | Qué agrega |
|---|---|
| `paso3_seguimiento.sql` | Historial y comentarios de las incidencias |
| `paso4_notificaciones.sql` | Notificaciones dentro del sistema |
| `paso6_catalogos.sql` | Columna `activo` en prioridades |
| `paso7_evidencias.sql` | Evidencias (archivos adjuntos) |
| `paso8_recuperar_password.sql` | Enlaces para restablecer contraseña |
| `paso9_ticket.sql` | Carrera, teléfono de contacto y tiempo de atención por prioridad |
| `paso10_carreras.sql` | *(Opcional)* Corrige carreras escritas a mano a su nombre oficial |

Los pasos 1, 2 y 5 no tienen migración (no cambian la base de datos). Todas las migraciones se pueden ejecutar más
de una vez sin duplicar datos.

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

Para que el ticket lleve el logo, guarda la imagen como `public/img/logo.png` (o `logo.jpg`).

### 4.3 Envío de correos (opcional)

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

1. La base de datos: phpMyAdmin → `sistema_incidencias` → **Exportar** → SQL.
2. La carpeta `storage/evidencias/` (fotos y PDF adjuntos por los usuarios).

## 6. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| "No se pudo conectar a la base de datos" | MySQL apagado, base inexistente o datos de conexión incorrectos | Inicia MySQL en XAMPP, crea la base (sección 2) y revisa `app/config/database.php`. El motivo exacto queda en `C:\xampp\apache\logs\error.log` |
| "Ocurrió un error al consultar la base de datos" | Normalmente faltan migraciones | Ejecuta las migraciones pendientes (sección 3); el detalle está en el log de Apache |
| No se pueden adjuntar archivos | Límite de `php.ini` | Sube `upload_max_filesize` y `post_max_size` |
| "Los archivos enviados superan el límite del servidor" | El envío total supera `post_max_size` | Adjunta menos archivos o más ligeros |
| Acentos raros en el ticket PDF | Extensión `iconv`/`mbstring` desactivada | Actívalas en `php.ini` |
| No llegan los correos | Correo sin configurar o bloqueado | Revisa la sección 4.3; mientras tanto el Administrador puede generar el enlace |
| La página muestra código PHP | Se abrió el archivo directamente | Usa `http://localhost/...`, no `file:///...` |
