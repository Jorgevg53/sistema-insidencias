# Guía de despliegue paso a paso

Hay dos formas de poner el sistema en marcha. Elige la que te corresponda:

- **Parte A — En una computadora del Departamento con XAMPP.** El sistema se usa desde esa computadora y desde las
  demás de la red de la escuela.
- **Parte B — En un hosting de internet.** El sistema se abre desde cualquier lugar con una dirección como
  `https://tudominio.com/sistema-incidencias/`.

Después sigue la **Parte C** para verificar que todo funcione. Si ya tenías el sistema instalado con datos, usa la
**Parte D**.

---

## Parte A — XAMPP (computadora del Departamento o red local)

### A1. Descargar el proyecto

1. Entra al repositorio en GitHub.
2. Pulsa **Code → Download ZIP** (en la rama `main` una vez aceptado el Pull Request).
3. Descomprime el ZIP.

> El ZIP de GitHub no incluye la carpeta oculta `.git`, que no debe subirse a ningún servidor.

### A2. Instalar XAMPP

1. Descarga **XAMPP con PHP 8.2** desde <https://www.apachefriends.org/es/>.
2. Instálalo en `C:\xampp` (ruta predeterminada) con al menos **Apache**, **MySQL**, **PHP** y **phpMyAdmin**.

### A3. Copiar el proyecto

1. Renombra la carpeta descomprimida como **`sistema-incidencias`**.
2. Cópiala a `C:\xampp\htdocs\`.
3. Comprueba que quede así (sin carpetas repetidas en medio):

   ```
   C:\xampp\htdocs\sistema-incidencias\
       app\  database\  docs\  public\  storage\  index.php  README.md
   ```

### A4. Revisar la configuración de PHP

1. Abre el **Panel de control de XAMPP** → botón **Config** de Apache → **PHP (php.ini)**.
2. Busca estas líneas y verifica que **no** empiecen con `;` (si lo tienen, bórralo):

   ```ini
   extension=fileinfo
   extension=mbstring
   extension=pdo_mysql
   ```

3. Verifica los límites para adjuntar archivos:

   ```ini
   upload_max_filesize = 40M
   post_max_size = 40M
   ```

4. Guarda el archivo.

### A5. Iniciar los servicios

En el Panel de control de XAMPP pulsa **Start** en **Apache** y en **MySQL** (deben quedar en verde).
Si ya estaban encendidos, pulsa **Stop** y luego **Start** en Apache para que tome los cambios de `php.ini`.

### A6. Crear la base de datos

1. Abre <http://localhost/phpmyadmin>.
2. En el panel izquierdo pulsa **Nueva**.
3. Nombre: **`sistema_incidencias`** · Cotejamiento: **`utf8mb4_unicode_ci`** → **Crear**.
4. Con la base seleccionada, abre la pestaña **Importar** → **Seleccionar archivo** →
   `C:\xampp\htdocs\sistema-incidencias\database\database.sql` → **Importar** (o **Continuar**).
5. Deben aparecer **11 tablas**: categorias, comentarios_incidencia, estados_incidencia, evidencias,
   historial_incidencias, incidencias, notificaciones, prioridades, restablecimientos_password, roles y usuarios.

> **Empezar sin datos de prueba (recomendado):** el script trae 3 incidencias de las pruebas iniciales. Para
> borrarlas, abre la pestaña **SQL** de la base, pega lo siguiente y pulsa **Continuar**:
>
> ```sql
> DELETE FROM incidencias;
> ALTER TABLE incidencias AUTO_INCREMENT = 1;
> ```
>
> Se borran también su historial, comentarios y notificaciones. Los usuarios y catálogos no se tocan.

### A7. Conectar el sistema con la base de datos

Abre `app\config\database.php` con el Bloc de notas y revisa:

```php
private $host = "localhost";
private $db_name = "sistema_incidencias";
private $username = "root";
private $password = "";
```

Con XAMPP recién instalado estos valores ya son correctos.

### A8. Primer inicio de sesión

1. Abre <http://localhost/sistema-incidencias/>. Te enviará al inicio de sesión.
2. Entra con **`admin@teschi.edu.mx`** y la contraseña del Administrador.

> **¿No conoces la contraseña del Administrador?** Genera una nueva:
>
> 1. Abre **Símbolo del sistema** (cmd) y ejecuta (cambia `NuevaClave2026` por la que quieras):
>
>    ```
>    C:\xampp\php\php.exe -r "echo password_hash('NuevaClave2026', PASSWORD_DEFAULT);"
>    ```
>
> 2. Copia el texto que empieza con `$2y$`.
> 3. En phpMyAdmin → base `sistema_incidencias` → pestaña **SQL**:
>
>    ```sql
>    UPDATE usuarios SET password = 'PEGA_AQUI_EL_TEXTO' WHERE correo = 'admin@teschi.edu.mx';
>    ```

### A9. Configuración inicial dentro del sistema

1. **Mi perfil** → cambia la contraseña del Administrador.
2. **Catálogos** → revisa categorías, prioridades y tiempos de atención.
3. **Usuarios** → crea las cuentas (coordinadores, docentes, personal administrativo y estudiantes).
4. *(Opcional)* Edita `app\config\institucion.php` (textos del ticket y lista de carreras). Los logotipos del
   ticket están en `public\img\logo_edomex.png` y `public\img\logo_teschi.png`.

### A10. Usarlo desde otras computadoras de la red

1. En la computadora con XAMPP abre **cmd** y ejecuta `ipconfig`. Anota la **Dirección IPv4** (por ejemplo,
   `192.168.1.50`). Pide al área de sistemas que le asigne esa IP de forma **fija**.
2. Permite Apache en el firewall: **Firewall de Windows** → **Permitir una aplicación** →
   marca **Apache HTTP Server** en **Privada**. Windows también suele preguntarlo la primera vez que inicia Apache.
3. En las demás computadoras abre `http://192.168.1.50/sistema-incidencias/`.
4. Para que arranque solo al encender la computadora: abre el Panel de control de XAMPP **como administrador** y
   marca la casilla **Svc** de Apache y de MySQL (los instala como servicios de Windows).

### A11. Seguridad mínima en la red

1. **Contraseña de MySQL:** phpMyAdmin → **Cuentas de usuarios** → `root` / `localhost` → **Editar privilegios** →
   **Cambiar contraseña**. Escríbela también en `app\config\database.php` (`$password`).
2. **Ocultar errores técnicos:** en `php.ini` cambia `display_errors = On` por `display_errors = Off` y reinicia Apache.
3. phpMyAdmin de XAMPP solo se puede abrir desde la misma computadora (`localhost`); no lo cambies.

### A12. Respaldos

Programa un respaldo semanal de:

1. **La base de datos:** phpMyAdmin → `sistema_incidencias` → **Exportar** → **Continuar** (archivo `.sql`).
2. **La carpeta** `C:\xampp\htdocs\sistema-incidencias\storage\evidencias\` (fotos y PDF que suben los usuarios).

---

## Parte B — Hosting en internet

Sirve cualquier hosting con **PHP 8.0 o superior**, **MySQL 5.7/8 o MariaDB**, **phpMyAdmin** y **Apache**
(la mayoría de los planes con cPanel, hPanel o similares).

### B1. Configurar PHP en el hosting

En el panel del hosting (sección *PHP*, *Select PHP Version* o *MultiPHP*):

1. Elige **PHP 8.2** (u 8.1 / 8.3).
2. Activa las extensiones **pdo_mysql**, **mbstring**, **fileinfo** e **iconv** (casi siempre vienen activas).
3. En las opciones de PHP: `display_errors = Off`, `upload_max_filesize = 40M`, `post_max_size = 40M`.

### B2. Crear la base de datos

1. En el panel: **Bases de datos MySQL** → crea una base (por ejemplo `incidencias`).
2. Crea un **usuario** con contraseña segura.
3. **Asigna el usuario a la base** con **todos los privilegios**.
4. Anota los datos **completos**. Muchos hostings agregan un prefijo, por ejemplo:

   | Dato | Ejemplo |
   |---|---|
   | Servidor (host) | `localhost` (algunos dan uno propio, como `sql123.servidor.com`) |
   | Base de datos | `u123456_incidencias` |
   | Usuario | `u123456_admin` |
   | Contraseña | la que definiste |

### B3. Importar las tablas

1. Abre **phpMyAdmin** desde el panel y selecciona tu base en la columna izquierda.
2. Pestaña **Importar** → `database/database.sql` → **Continuar**.
3. Verifica que aparezcan las **11 tablas**. Opcional: borra las incidencias de prueba (ver A6).

### B4. Configurar la conexión

Antes de subir los archivos, edita `app/config/database.php` en tu computadora con los datos de B2:

```php
private $host = "localhost";                 // o el servidor que te dio el hosting
private $db_name = "u123456_incidencias";
private $username = "u123456_admin";
private $password = "TuContraseñaSegura";
```

### B5. Subir los archivos

1. Vuelve a comprimir la carpeta `sistema-incidencias` en un ZIP (con `app`, `public`, `storage`, etc. adentro).
2. Panel → **Administrador de archivos** → entra a **`public_html`** → **Cargar** el ZIP → **Extraer**.
3. Debe quedar `public_html/sistema-incidencias/app`, `public_html/sistema-incidencias/public`, etc.
4. La dirección será `https://tudominio.com/sistema-incidencias/`.

> **Opción recomendada si tu hosting lo permite:** crea un **subdominio** (por ejemplo `incidencias.tudominio.com`)
> y apunta su carpeta raíz (*Document root*) a `sistema-incidencias/public`. Así el navegador solo puede ver la carpeta
> pública.

### B6. Permisos de carpetas

En el Administrador de archivos, la carpeta `storage/evidencias` debe tener permisos **755** (si al adjuntar archivos
aparece un error, prueba con **775**). No uses 777.

### B7. Activar HTTPS

En el panel busca **SSL** o **Let's Encrypt** y actívalo para tu dominio. Desde ese momento usa siempre
`https://`, así las contraseñas viajan cifradas.

### B8. Correo para recuperar contraseñas (opcional)

Sin configurar nada, la recuperación funciona a través del Administrador. Para enviarla por correo, crea
`app/config/correo.local.php` como se explica en la [Guía de instalación](01_instalacion.md) (sección 4.4), con:

- los datos SMTP de tu hosting o de Gmail;
- `"url_base" => "https://tudominio.com/sistema-incidencias/public"`.

> Algunos hostings gratuitos bloquean el envío de correo; en ese caso usa el método del Administrador.

### B9. Primer inicio de sesión

Igual que en A8 y A9. Para cambiar la contraseña del Administrador sin acceso a una terminal, genera el texto
`$2y$…` en cualquier computadora con XAMPP (A8) y ejecuta el `UPDATE` en el phpMyAdmin del hosting.

---

## Parte C — Verificación final

Marca cada punto:

- [ ] `http://…/sistema-incidencias/` envía al inicio de sesión.
- [ ] El Administrador inicia sesión y ve el Dashboard.
- [ ] Se crea un usuario de prueba de cada rol.
- [ ] Un estudiante registra una incidencia **con una foto adjunta**.
- [ ] El coordinador la ve en *Todas las incidencias*, le asigna responsable y le llega el aviso al responsable
      (campana).
- [ ] El responsable la marca *En proceso* y luego *Resuelta*.
- [ ] Se descarga el **ticket en PDF** con acentos correctos.
- [ ] **Reportes** muestra gráficas y exporta el CSV.
- [ ] Estas direcciones deben responder **403 Forbidden / Acceso prohibido** (están protegidas):
      `…/sistema-incidencias/app/config/database.php`, `…/sistema-incidencias/database/database.sql`,
      `…/sistema-incidencias/storage/evidencias/`.
- [ ] Se borran los usuarios e incidencias de prueba (los usuarios se **desactivan** en *Usuarios*).

Si algo falla, revisa la tabla de **Solución de problemas** de la [Guía de instalación](01_instalacion.md) y el log
de errores (`C:\xampp\apache\logs\error.log` en XAMPP, o *Registros de errores* en el panel del hosting).

---

## Parte D — Actualizar un sistema que ya tiene datos

1. **Respalda** la base de datos (Exportar) y la carpeta `storage/evidencias/`.
2. Guarda aparte tus archivos de configuración: `app/config/database.php` y, si existe, `app/config/correo.local.php`.
3. Reemplaza los archivos del proyecto por la versión nueva, **sin borrar** `storage/evidencias/`.
4. Vuelve a colocar tus dos archivos de configuración.
5. En phpMyAdmin → pestaña **SQL**, ejecuta **en orden** las migraciones de `database/migraciones/` que te falten
   (ver la tabla de la [Guía de instalación](01_instalacion.md), sección 3). **No** importes `database.sql` sobre
   una base con datos.

> **Hosting con MySQL (no MariaDB):** en `paso6_catalogos.sql` y `paso9_ticket.sql` borra las palabras
> `IF NOT EXISTS` que aparecen después de `ADD COLUMN` antes de ejecutarlas (esa forma solo existe en MariaDB).
> Ejecuta cada una solo una vez.

6. Revisa la Parte C.
