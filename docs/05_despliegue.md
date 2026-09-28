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

### A2. Instalar XAMPP y MongoDB

1. Descarga **XAMPP con PHP 8.2 o superior** desde <https://www.apachefriends.org/es/>.
2. Instálalo en `C:\xampp` (ruta predeterminada) con al menos **Apache** y **PHP**.
3. Instala la **extensión `mongodb` de PHP** y el **servidor MongoDB Community** siguiendo las
   secciones 1.1 y 1.2 de la [Guía de instalación](01_instalacion.md). (MongoDB reemplaza a MySQL:
   ya no se usa phpMyAdmin.)

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
   extension=mongodb
   ```

   La línea `extension=mongodb` la agregas tú al instalar la extensión (sección 1.1 de la
   Guía de instalación); no viene en XAMPP.

3. Verifica los límites para adjuntar archivos:

   ```ini
   upload_max_filesize = 40M
   post_max_size = 40M
   ```

4. Guarda el archivo.

### A5. Iniciar los servicios

En el Panel de control de XAMPP pulsa **Start** en **Apache** (debe quedar en verde). Asegúrate de que el
**servicio de MongoDB** esté en ejecución (se instala como servicio de Windows). Si cambiaste `php.ini`,
pulsa **Stop** y luego **Start** en Apache para que tome los cambios.

### A6. Crear las colecciones y los datos

1. Abre **Símbolo del sistema** (cmd).
2. Ejecuta el inicializador (crea las colecciones, los índices, los contadores y datos de ejemplo):

   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\sistema-incidencias\database\seed_mongo.php
   ```

3. Al terminar muestra las cuentas creadas. En la base `sistema_incidencias` quedan las colecciones:
   `usuarios`, `incidencias`, `notificaciones`, `restablecimientos`, `roles`, `estados`, `categorias`,
   `prioridades` y `contadores`.

> **Reiniciar los datos:** el inicializador no borra nada si ya hay datos. Para volver a los datos de
> ejemplo, ejecútalo agregando `force` al final:
>
> ```
> C:\xampp\php\php.exe C:\xampp\htdocs\sistema-incidencias\database\seed_mongo.php force
> ```

### A7. Conectar el sistema con la base de datos

Abre `app\config\database.php` con el Bloc de notas y revisa:

```php
$this->uri = getenv("MONGODB_URI") ?: "mongodb://127.0.0.1:27017";
$this->db_name = getenv("MONGODB_DB") ?: "sistema_incidencias";
```

Con MongoDB local recién instalado estos valores ya son correctos. Para **MongoDB Atlas**, cambia la URI por
la de tu clúster (`mongodb+srv://usuario:clave@...`) o define las variables de entorno `MONGODB_URI` y `MONGODB_DB`.

### A8. Primer inicio de sesión

1. Abre <http://localhost/sistema-incidencias/>. Te enviará al inicio de sesión.
2. Entra con **`admin@teschi.edu.mx`** y la contraseña **`Admin1234`** que crea el inicializador.
   Cámbiala enseguida en **Mi perfil**.

> **¿Olvidaste la contraseña del Administrador?** Genera un hash nuevo y actualízalo:
>
> 1. En **cmd**: `C:\xampp\php\php.exe -r "echo password_hash('NuevaClave2026', PASSWORD_DEFAULT);"`
> 2. Copia el texto que empieza con `$2y$`.
> 3. En **mongosh** (la consola de MongoDB):
>
>    ```
>    use sistema_incidencias
>    db.usuarios.updateOne({ correo: "admin@teschi.edu.mx" }, { $set: { password: "PEGA_AQUI_EL_TEXTO" } })
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
   marca la casilla **Svc** de Apache (MongoDB ya corre como su propio servicio de Windows).

### A11. Seguridad mínima en la red

1. **Autenticación de MongoDB:** en producción crea un usuario y contraseña en MongoDB y usa una URI con
   credenciales (`mongodb://usuario:clave@127.0.0.1:27017`) en `MONGODB_URI`. En una red pequeña de confianza puede
   quedar sin autenticar, pero **nunca** expongas el puerto `27017` a internet.
2. **Ocultar errores técnicos:** en `php.ini` cambia `display_errors = On` por `display_errors = Off` y reinicia Apache.
3. El servidor de MongoDB solo debe aceptar conexiones locales (`bindIp: 127.0.0.1` en su configuración).

### A12. Respaldos

Programa un respaldo semanal de:

1. **La base de datos:** `mongodump --db sistema_incidencias --out C:\respaldos\incidencias`
   (para restaurar: `mongorestore`).
2. **La carpeta** `C:\xampp\htdocs\sistema-incidencias\storage\evidencias\` (fotos y PDF que suben los usuarios).

---

## Parte B — Hosting en internet

Sirve cualquier hosting con **PHP 8.1 o superior** que permita activar la extensión **`mongodb`**, más un
servidor **MongoDB**. Lo más práctico es un clúster gratuito de **MongoDB Atlas** al que se conecta el hosting.
(No todos los hostings compartidos incluyen la extensión `mongodb`; si el tuyo no la trae, usa un VPS o un
servicio con soporte para MongoDB.)

### B1. Configurar PHP en el hosting

En el panel del hosting (sección *PHP*, *Select PHP Version* o *MultiPHP*):

1. Elige **PHP 8.2** (u 8.1 / 8.3).
2. Activa las extensiones **mongodb**, **mbstring**, **fileinfo** e **iconv**.
3. En las opciones de PHP: `display_errors = Off`, `upload_max_filesize = 40M`, `post_max_size = 40M`.

### B2. Preparar la base de datos (MongoDB Atlas)

1. Crea una cuenta en <https://www.mongodb.com/atlas> y un **clúster gratuito (M0)**.
2. En **Database Access** crea un usuario de base de datos con contraseña segura.
3. En **Network Access** permite la IP del hosting (o `0.0.0.0/0` solo si es imprescindible).
4. En **Connect → Drivers** copia la **cadena de conexión**:
   `mongodb+srv://usuario:clave@cluster0.xxxx.mongodb.net`.

### B3. Crear las colecciones

- Si el hosting te da acceso por **SSH**, ejecuta el inicializador una vez apuntando a tu clúster:

  ```
  MONGODB_URI="mongodb+srv://usuario:clave@cluster0.xxxx.mongodb.net" php database/seed_mongo.php
  ```

- Si **no** tienes SSH, ejecútalo en tu computadora con esa misma `MONGODB_URI`: creará las colecciones
  directamente en Atlas. Opcional: usa `... seed_mongo.php force` para reiniciar los datos de ejemplo.

### B4. Configurar la conexión

Define en tu hosting las variables de entorno **`MONGODB_URI`** (la cadena de Atlas) y **`MONGODB_DB`**
(`sistema_incidencias`). Si el hosting no permite variables de entorno, edita `app/config/database.php`:

```php
$this->uri = "mongodb+srv://usuario:clave@cluster0.xxxx.mongodb.net";
$this->db_name = "sistema_incidencias";
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

Igual que en A8 y A9 (`admin@teschi.edu.mx` / `Admin1234`; cámbiala en *Mi perfil*). Para restablecer la
contraseña del Administrador, genera el texto `$2y$…` (A8) y actualiza el campo `password` del usuario en la
colección `usuarios`, con **mongosh** o desde la vista *Collections* de MongoDB Atlas.

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
      `…/sistema-incidencias/app/config/database.php`, `…/sistema-incidencias/database/seed_mongo.php`,
      `…/sistema-incidencias/storage/evidencias/`.
- [ ] Se borran los usuarios e incidencias de prueba (los usuarios se **desactivan** en *Usuarios*).

Si algo falla, revisa la tabla de **Solución de problemas** de la [Guía de instalación](01_instalacion.md) y el log
de errores (`C:\xampp\apache\logs\error.log` en XAMPP, o *Registros de errores* en el panel del hosting).

---

## Parte D — Actualizar un sistema que ya tiene datos

1. **Respalda** la base de datos (`mongodump`) y la carpeta `storage/evidencias/`.
2. Guarda aparte tu configuración: `app/config/database.php` (o tus variables `MONGODB_URI` / `MONGODB_DB`)
   y, si existe, `app/config/correo.local.php`.
3. Reemplaza los archivos del proyecto por la versión nueva, **sin borrar** `storage/evidencias/`.
4. Vuelve a colocar tu configuración.
5. Como los datos viven en MongoDB, al actualizar solo el código normalmente **no** hay que tocar la base.
   Si una versión nueva pide un cambio de estructura, se indicará en sus notas. **No** ejecutes
   `seed_mongo.php` sin `force` sobre una base con datos reales (y con `force` los borra).
6. Revisa la Parte C.
