# Sistema de Gestión de Incidencias · TESCHI

Departamento de Ciencias Básicas — Tecnológico de Estudios Superiores de Chimalhuacán.

Aplicación web en PHP + MySQL/MariaDB (PDO) para registrar, dar seguimiento y
atender incidencias.

## Documentación

La documentación completa está en [`docs/`](docs/README.md): **guía de despliegue paso a paso**
([`docs/05_despliegue.md`](docs/05_despliegue.md)), guía de instalación, manual de usuario con capturas,
base de datos (diagrama y diccionario de datos) y arquitectura y seguridad. También hay una versión en Word para
entregar: [`docs/Documentacion_Sistema_Incidencias_TESCHI.docx`](docs/Documentacion_Sistema_Incidencias_TESCHI.docx).

## Instalación (XAMPP)

1. Copia la carpeta del proyecto en `htdocs/sistema-incidencias`.
2. En phpMyAdmin crea la base `sistema_incidencias` e importa `database/database.sql`.
3. Revisa los datos de conexión en `app/config/database.php`.
4. Abre `http://localhost/sistema-incidencias/`.
5. Evidencias: los archivos se guardan en `storage/evidencias/` (la carpeta debe
   poder escribirse). En `php.ini` de XAMPP revisa que `upload_max_filesize` sea de al
   menos `5M` y `post_max_size` de al menos `30M` (XAMPP trae 40M por defecto).
   Al respaldar el sistema, copia también esa carpeta junto con la base de datos.
6. (Opcional) Envío de correos para recuperar contraseña: crea
   `app/config/correo.local.php` siguiendo las instrucciones de `app/config/correo.php`
   (Gmail con "contraseña de aplicación"). Ese archivo no se sube a git.

### Actualizar una base de datos existente

Si ya tenías la base instalada antes de un paso, ejecuta en phpMyAdmin (pestaña SQL)
los archivos de `database/migraciones/` que te falten, en orden:

- `paso3_seguimiento.sql` — tablas de historial y comentarios.
- `paso4_notificaciones.sql` — tabla de notificaciones.
- `paso6_catalogos.sql` — columna `activo` en prioridades.
- `paso7_evidencias.sql` — tabla de evidencias (archivos adjuntos).
- `paso8_recuperar_password.sql` — tabla de enlaces para restablecer contraseña.
- `paso9_ticket.sql` — carrera, teléfono de contacto y tiempo de atención por prioridad.
- `paso10_carreras.sql` — (opcional) corrige carreras escritas a mano a su nombre oficial.

## Estructura

```
app/
  config/database.php      Conexión PDO
  config/correo.php        SMTP opcional (credenciales en correo.local.php)
  config/institucion.php   Encabezado y leyendas del ticket en PDF
  lib/fpdf/                Librería FPDF 1.9 para generar PDF (sin Composer)
  helpers/ticket_pdf.php   Diseño del ticket de la incidencia
  helpers/carreras.php     Lista desplegable y validación de carreras
  helpers/paginacion.php   Paginación reutilizable (páginas y "por página")
  controllers/             AuthController (login)
  helpers/auth.php         Sesión, roles, CSRF, mensajes flash, escape HTML
  models/Usuario.php       Consultas de usuarios
  models/Incidencia.php    Estados, asignación, historial, comentarios y avisos
  models/Notificacion.php  Notificaciones (contar, listar, marcar leídas)
  models/Reporte.php       Estadísticas y tiempos de atención para Reportes
  models/Catalogo.php      Alta, edición y activación de categorías y prioridades
  models/Evidencia.php     Validación, guardado y consulta de archivos adjuntos
  helpers/evidencias.php   Campo para adjuntar y galería de evidencias
  helpers/correo.php       Cliente SMTP mínimo (STARTTLS/SSL, sin librerías)
  models/Restablecimiento.php  Enlaces de un solo uso para restablecer contraseña
  views/layouts/           Encabezado y pie comunes (menú según el rol)
  views/auth/login.php     Vista del login
database/database.sql      Instalación completa de la base de datos
database/migraciones/      Cambios para bases ya instaladas
storage/evidencias/        Archivos subidos (protegido con .htaccess, no se sube a git)
public/                    Páginas accesibles desde el navegador
```

## Roles y permisos

| Módulo                           | Administrador | Coordinador | Docente / Administrativo | Estudiante |
|----------------------------------|:-:|:-:|:-:|:-:|
| Registrar incidencia             | ✔ | ✔ | ✔ | ✔ |
| Mis incidencias / detalle        | ✔ | ✔ | ✔ (propias y asignadas) | ✔ (propias) |
| Comentar                         | ✔ | ✔ | ✔ | ✔ |
| Cancelar (propia y Pendiente)    | ✔ | ✔ | ✔ | ✔ |
| Asignadas a mí / atender         | ✔ | ✔ | ✔ | — |
| Todas las incidencias            | ✔ | ✔ | — | — |
| Reportes y exportación           | ✔ | ✔ | — | — |
| Cambiar cualquier estado/asignar | ✔ | ✔ | — | — |
| Reclasificar (categoría/prioridad) | ✔ | ✔ | — | — |
| Usuarios                         | ✔ | — | — | — |
| Catálogos                        | ✔ | — | — | — |
| Mi perfil (contraseña)           | ✔ | ✔ | ✔ | ✔ |

### Flujo de una incidencia

1. El usuario la registra → **Pendiente**.
2. Un gestor asigna un responsable → **Asignada** (automático si estaba Pendiente o En revisión).
3. El responsable la atiende → **En proceso** → **Resuelta** (debe describir la solución).
4. El gestor la revisa → **Cerrada** (ya no admite comentarios; puede reabrirse).

En cualquier momento el gestor puede **reclasificarla** (corregir categoría o prioridad)
desde "Gestionar incidencia"; el cambio queda en el seguimiento y se avisa a quien la
reportó y al responsable.

Cada cambio de estado, asignación y comentario queda en la línea de tiempo de la incidencia.

### ¿Quién recibe notificaciones?

| Evento | Destinatarios |
|---|---|
| Nueva incidencia | Administradores y Coordinadores |
| Asignación | El nuevo responsable (y el anterior, si se le quitó) |
| Cambio de estado | Quien reportó y el responsable |
| Pasa a Resuelta o Cancelada | Además, Administradores y Coordinadores |
| Comentario | Quien reportó y el responsable (si comenta quien reportó y no hay responsable: gestores) |

Nadie recibe avisos de sus propias acciones, y si en un mismo guardado hay varios cambios
se envía una sola notificación por persona. La campana del encabezado se actualiza cada minuto
y al abrir una incidencia sus avisos se marcan como leídos.

### Ticket en PDF

Cada incidencia tiene su ticket (botones **Ver ticket** / **Descargar ticket** en el
detalle y enlace **Ticket** en "Mis incidencias"), con los mismos datos del ticket en
papel del Departamento: institución y dirección, No. de ticket, fecha y hora, nombre del
docente o solicitante, carrera, No. de empleado o matrícula, teléfono, correo, solicitud,
descripción breve, tiempo estimado de atención (días hábiles según la prioridad, con fecha
límite), leyendas de garantía y aclaración, firmas del Departamento y de la persona
atendida, espacio para sello y "Gracias por su visita". Además incluye folio, categoría,
prioridad, ubicación, estado, responsable y evidencias.

- Textos del encabezado y leyendas: `app/config/institucion.php`.
- Logo opcional: coloca `public/img/logo.png` (o `.jpg`).
- Tiempo de atención por prioridad: *Catálogos → Prioridades*.
- Carrera y teléfono se capturan al registrar la incidencia (propuestos desde
  *Mi perfil*) y se guardan tal como estaban ese día.
- La carrera se elige de la lista oficial del TESCHI (`carreras` en
  `app/config/institucion.php`): Ingeniería en Animación Digital y Efectos Visuales,
  Ingeniería en Sistemas Computacionales, Ingeniería Industrial, Ingeniería Mecatrónica,
  Ingeniería Química, Licenciatura en Administración, Licenciatura en Gastronomía y
  Posgrado en Administración (o "No aplica"). Reportes incluye la gráfica **Por carrera**.

### Recuperar contraseña

- **Sin correo configurado (predeterminado):** en el login, "¿Olvidaste tu contraseña?"
  avisa a los Administradores. El Administrador abre al usuario en *Usuarios → Editar*
  y pulsa **Generar enlace** (válido 24 h, un solo uso) para entregárselo.
- **Con correo configurado:** el enlace (válido 60 min) llega directo al usuario.
- La respuesta es la misma exista o no el correo, hay límite de solicitudes por hora,
  el token solo se guarda como hash SHA-256, cada enlace sirve una vez y al generar uno
  nuevo se anulan los anteriores. El login muestra un único mensaje de error.

## Avance

- [x] **Paso 1 – Base:** layout común, estilos, helper de sesión/roles, CSRF,
      corrección de errores, filtros y fecha de cierre.
- [x] **Paso 2 – Usuarios:** alta, edición, activar/desactivar y cambio de contraseña.
- [x] **Paso 3 – Seguimiento:** asignar responsable, historial de cambios de estado
      y comentarios en cada incidencia (tablas nuevas).
- [x] **Paso 4 – Notificaciones:** avisos dentro del sistema cuando cambia una incidencia.
- [x] **Paso 5 – Reportes:** indicadores y tiempos de atención, gráficas por estado,
      prioridad, categoría, ubicación y mes, desempeño por responsable, exportar a
      Excel (CSV) e imprimir / guardar como PDF. No requiere cambios en la base de datos.
- [x] **Paso 6 – Catálogos:** administrar categorías y prioridades desde el sistema
      (los estados y roles no se editan porque el flujo y los permisos dependen de sus nombres).
- [x] **Extra – Evidencias:** adjuntar JPG, PNG, WEBP o PDF (máx. 5 archivos de 5 MB)
      al registrar, comentar, gestionar o atender. El tipo se valida por contenido,
      los archivos se guardan con nombre aleatorio fuera de `public/` y solo los
      descarga quien puede ver la incidencia.
- [x] **Extra – Reclasificar incidencias:** el gestor corrige categoría y prioridad; queda
      en el historial (sin cambios en la base de datos).
- [x] **Extra – Paginación:** Todas las incidencias, Mis incidencias, Asignadas a mí,
      Usuarios y Notificaciones muestran 10/20/50/100 registros por página y conservan
      los filtros al cambiar de página (sin cambios en la base de datos).
- [x] **Extra – Ticket en PDF:** comprobante imprimible de cada incidencia con los datos
      del ticket en papel del Departamento.
- [x] **Extra – Recuperar contraseña:** enlace de un solo uso por correo (opcional) o
      generado por el Administrador.
