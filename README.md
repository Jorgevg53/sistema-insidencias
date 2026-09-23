# Sistema de Gestión de Incidencias · TESCHI

Departamento de Ciencias Básicas — Tecnológico de Estudios Superiores de Chimalhuacán.

Aplicación web en PHP + MySQL/MariaDB (PDO) para registrar, dar seguimiento y
atender incidencias.

## Instalación (XAMPP)

1. Copia la carpeta del proyecto en `htdocs/sistema-incidencias`.
2. En phpMyAdmin crea la base `sistema_incidencias` e importa `database/database.sql`.
3. Revisa los datos de conexión en `app/config/database.php`.
4. Abre `http://localhost/sistema-incidencias/public/`.

## Estructura

```
app/
  config/database.php      Conexión PDO
  controllers/             AuthController (login)
  helpers/auth.php         Sesión, roles, CSRF, mensajes flash, escape HTML
  models/Usuario.php       Consultas de usuarios
  views/layouts/           Encabezado y pie comunes (menú según el rol)
  views/auth/login.php     Vista del login
database/database.sql      Volcado de la base de datos
public/                    Páginas accesibles desde el navegador
```

## Roles y permisos

| Módulo                    | Administrador | Coordinador | Docente / Administrativo / Estudiante |
|---------------------------|:-------------:|:-----------:|:-------------------------------------:|
| Registrar incidencia      | ✔ | ✔ | ✔ |
| Mis incidencias / detalle | ✔ | ✔ | ✔ (solo las propias) |
| Todas las incidencias     | ✔ | ✔ | — |
| Cambiar estado            | ✔ | ✔ | — |
| Usuarios                  | ✔ | — | — |
| Mi perfil (contraseña)    | ✔ | ✔ | ✔ |

## Avance

- [x] **Paso 1 – Base:** layout común, estilos, helper de sesión/roles, CSRF,
      corrección de errores, filtros y fecha de cierre.
- [x] **Paso 2 – Usuarios:** alta, edición, activar/desactivar y cambio de contraseña.
- [ ] **Paso 3 – Seguimiento:** asignar responsable, historial de cambios de estado
      y comentarios en cada incidencia (tablas nuevas).
- [ ] **Paso 4 – Notificaciones:** avisos dentro del sistema cuando cambia una incidencia.
- [ ] **Paso 5 – Reportes:** estadísticas por estado, categoría y periodo; exportar a CSV/PDF.
- [ ] **Paso 6 – Catálogos:** administrar categorías y prioridades desde el sistema.
- [ ] **Extra:** adjuntar evidencias (imágenes) a las incidencias.
