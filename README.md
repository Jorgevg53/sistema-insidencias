# Sistema de Gestión de Incidencias · TESCHI

Departamento de Ciencias Básicas — Tecnológico de Estudios Superiores de Chimalhuacán.

Aplicación web en PHP + MySQL/MariaDB (PDO) para registrar, dar seguimiento y
atender incidencias.

## Instalación (XAMPP)

1. Copia la carpeta del proyecto en `htdocs/sistema-incidencias`.
2. En phpMyAdmin crea la base `sistema_incidencias` e importa `database/database.sql`.
3. Revisa los datos de conexión en `app/config/database.php`.
4. Abre `http://localhost/sistema-incidencias/public/`.

### Actualizar una base de datos existente

Si ya tenías la base instalada antes de un paso, ejecuta en phpMyAdmin (pestaña SQL)
los archivos de `database/migraciones/` que te falten, en orden:

- `paso3_seguimiento.sql` — tablas de historial y comentarios.

## Estructura

```
app/
  config/database.php      Conexión PDO
  controllers/             AuthController (login)
  helpers/auth.php         Sesión, roles, CSRF, mensajes flash, escape HTML
  models/Usuario.php       Consultas de usuarios
  models/Incidencia.php    Estados, asignación, historial y comentarios
  views/layouts/           Encabezado y pie comunes (menú según el rol)
  views/auth/login.php     Vista del login
database/database.sql      Instalación completa de la base de datos
database/migraciones/      Cambios para bases ya instaladas
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
| Cambiar cualquier estado/asignar | ✔ | ✔ | — | — |
| Usuarios                         | ✔ | — | — | — |
| Mi perfil (contraseña)           | ✔ | ✔ | ✔ | ✔ |

### Flujo de una incidencia

1. El usuario la registra → **Pendiente**.
2. Un gestor asigna un responsable → **Asignada** (automático si estaba Pendiente o En revisión).
3. El responsable la atiende → **En proceso** → **Resuelta** (debe describir la solución).
4. El gestor la revisa → **Cerrada** (ya no admite comentarios; puede reabrirse).

Cada cambio de estado, asignación y comentario queda en la línea de tiempo de la incidencia.

## Avance

- [x] **Paso 1 – Base:** layout común, estilos, helper de sesión/roles, CSRF,
      corrección de errores, filtros y fecha de cierre.
- [x] **Paso 2 – Usuarios:** alta, edición, activar/desactivar y cambio de contraseña.
- [x] **Paso 3 – Seguimiento:** asignar responsable, historial de cambios de estado
      y comentarios en cada incidencia (tablas nuevas).
- [ ] **Paso 4 – Notificaciones:** avisos dentro del sistema cuando cambia una incidencia.
- [ ] **Paso 5 – Reportes:** estadísticas por estado, categoría y periodo; exportar a CSV/PDF.
- [ ] **Paso 6 – Catálogos:** administrar categorías y prioridades desde el sistema.
- [ ] **Extra:** adjuntar evidencias (imágenes) a las incidencias.
