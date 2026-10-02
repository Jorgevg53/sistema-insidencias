# Manual de usuario

Sistema de Gestión de Incidencias · Departamento de Ciencias Básicas · TESCHI

> Las imágenes de este manual usan datos ficticios de demostración y corresponden a la **versión actual** del sistema
> (diseño institucional en verde, con los logotipos del TESCHI). Si quieres probar el sistema sin instalar nada,
> abre el [prototipo navegable](prototipo/README.md).

## Contenido

1. [Roles del sistema](#1-roles-del-sistema)
2. [Acceso al sistema](#2-acceso-al-sistema)
3. [Pantalla principal](#3-pantalla-principal)
4. [Registrar una incidencia](#4-registrar-una-incidencia)
5. [Consultar mis incidencias](#5-consultar-mis-incidencias)
6. [Detalle y seguimiento](#6-detalle-y-seguimiento)
7. [Ticket en PDF](#7-ticket-en-pdf)
8. [Atender incidencias asignadas](#8-atender-incidencias-asignadas)
9. [Gestionar incidencias (Coordinador y Administrador)](#9-gestionar-incidencias-coordinador-y-administrador)
10. [Reportes](#10-reportes)
11. [Notificaciones](#11-notificaciones)
12. [Mi perfil](#12-mi-perfil)
13. [Administración de usuarios](#13-administración-de-usuarios)
14. [Catálogos](#14-catálogos)
15. [Estados de una incidencia](#15-estados-de-una-incidencia)
16. [Preguntas frecuentes](#16-preguntas-frecuentes)

---

## 1. Roles del sistema

| Rol | Qué puede hacer |
|---|---|
| **Estudiante** | Registrar incidencias, consultar las suyas, comentar, adjuntar evidencias, cancelarlas mientras estén *Pendientes* y descargar su ticket. |
| **Docente** / **Administrativo** | Todo lo anterior y, además, **atender** las incidencias que le asignen (*Asignadas a mí*). |
| **Coordinador** | Todo lo anterior y, además, ver **todas** las incidencias, asignar responsables, cambiar estados, reclasificar y consultar **Reportes**. |
| **Administrador** | Todo lo anterior y, además, administrar **Usuarios**, **Catálogos** y restablecer contraseñas. |

El menú superior solo muestra las opciones de tu rol.

## 2. Acceso al sistema

### 2.1 Iniciar sesión

Escribe tu correo institucional y tu contraseña. Si los datos no coinciden verás
*"Correo o contraseña incorrectos"*.

La pantalla de acceso muestra de fondo el edificio del TESCHI. Si la cuenta está desactivada o la contraseña es
incorrecta, el sistema muestra el mismo aviso para no revelar qué cuentas existen.

![Inicio de sesión](img/01_login.png)

### 2.2 ¿Olvidaste tu contraseña?

1. En el inicio de sesión pulsa **¿Olvidaste tu contraseña?** y escribe tu correo.
2. Según cómo esté configurado el sistema:
   - **Con correo:** recibirás un enlace válido por **60 minutos**.
   - **Sin correo:** el Administrador recibe un aviso y te hará llegar un enlace válido por **24 horas**.
3. Abre el enlace y escribe tu nueva contraseña (mínimo 8 caracteres).

Cada enlace funciona **una sola vez**. Por seguridad, el mensaje es el mismo aunque el correo no esté registrado,
y solo se permiten 3 solicitudes por hora.

![Recuperar contraseña](img/02_recuperar.png)

### 2.3 Crear una nueva contraseña

Al abrir el enlace que recibiste aparece la pantalla **Nueva contraseña**. Escribe la contraseña dos veces
(mínimo 8 caracteres) y pulsa **Guardar contraseña**. Después inicia sesión con ella. Si el enlace ya se usó o
caducó, el sistema te lo indica y puedes solicitar uno nuevo.

![Nueva contraseña](img/20_restablecer.png)

## 3. Pantalla principal

El **Dashboard** muestra un resumen de incidencias por estado (las tuyas o, para Coordinador y Administrador,
las de todo el departamento) y accesos directos a los módulos.

En la barra superior están la **campana de notificaciones** (con el número de avisos sin leer), tu nombre, tu rol
y **Cerrar sesión**.

![Dashboard](img/03_dashboard.png)

### 3.1 Elementos de la pantalla

| Elemento | Dónde está | Para qué sirve |
|---|---|---|
| **Logotipo del TESCHI** | Esquina superior izquierda | Te lleva al Dashboard desde cualquier pantalla. |
| **Campana** | Barra superior | Muestra con un globo naranja cuántos avisos tienes sin leer; al pulsarla abres *Notificaciones*. |
| **Nombre y rol** | Barra superior | Indica con qué cuenta estás dentro y qué rol tienes. |
| **Cerrar sesión** | Barra superior | Termina tu sesión. Hazlo siempre en computadoras compartidas. |
| **Menú** | Franja verde oscuro | Solo muestra los módulos de tu rol. La sección donde estás aparece resaltada en **verde claro** y el menú se queda fijo al bajar por la página. |
| **Tarjetas** | Contenido | Cada bloque de información va en una tarjeta con borde verde arriba. |
| **Etiquetas de color** | Tablas y detalle | El color indica el estado (*Pendiente* amarillo, *Resuelta* verde, *Cancelada* rojo, etc.) y la prioridad. |
| **Botones** | Formularios | El botón principal es verde con degradado; los secundarios son blancos; las acciones que borran o cancelan son rojas. |

## 4. Registrar una incidencia

Menú **Registrar incidencia**.

| Campo | Obligatorio | Descripción |
|---|:---:|---|
| Título | ✔ | Resumen corto del problema o solicitud (aparece como "Solicitud" en el ticket). |
| Categoría | ✔ | Académica, Control Escolar, Equipo de cómputo, Redes, etc. |
| Prioridad | ✔ | Baja, Media, Alta o Crítica. Define el **tiempo estimado de atención**. |
| Carrera | | Se elige de la lista oficial de carreras (o "No aplica"). Se propone la de tu perfil. |
| Teléfono de contacto | | Se propone el de tu perfil. |
| Ubicación | | Aula, laboratorio, edificio… |
| Descripción | ✔ | Explica el problema con detalle. Puedes escribir listas (por ejemplo, alumnos y matrículas). |
| Evidencias | | Hasta 5 archivos JPG, PNG, WEBP o PDF de máximo 5 MB cada uno. |

Al guardar, la incidencia queda **Pendiente**, recibe un **folio** (por ejemplo `INC-20260923-4F2A9C`) y los
Coordinadores reciben un aviso.

![Registrar incidencia](img/04_registrar_incidencia.png)

> El botón para adjuntar archivos aparece con el texto del idioma de tu navegador
> (por ejemplo, "Seleccionar archivos").

## 5. Consultar mis incidencias

Menú **Mis incidencias**: lista de tus reportes con folio, categoría, prioridad, estado y fecha. Desde cada fila puedes
abrir el **detalle** o el **ticket** en PDF. Las listas largas se dividen en páginas (10, 20, 50 o 100 por página).

![Mis incidencias](img/05_mis_incidencias.png)

Abajo de la tabla está el selector **Por página** y los números de página; los filtros que hayas aplicado se conservan.

## 6. Detalle y seguimiento

Al abrir una incidencia verás:

- **Datos**: quién la reportó, número de empleado o matrícula, carrera, teléfono, categoría, prioridad, ubicación,
  responsable, fechas y **tiempo estimado de atención** (en días hábiles, con fecha límite).
- **Evidencias** adjuntas al registrarla.
- **Seguimiento**: línea de tiempo con cada cambio de estado, asignación, reclasificación y comentario,
  con quién lo hizo y cuándo.
- **Agregar comentario**: texto, archivos o ambos. Las incidencias *Cerradas* o *Canceladas* ya no admiten comentarios.
- **Cancelar incidencia**: solo quien la reportó y solo mientras esté *Pendiente*.

Al abrir una incidencia, sus notificaciones se marcan como leídas.

![Detalle de una incidencia](img/06_detalle_incidencia.png)

### Eliminar una evidencia

Quien subió el archivo puede eliminarlo mientras la incidencia admita comentarios; el Coordinador y el Administrador
pueden hacerlo siempre. La eliminación queda registrada en el seguimiento.

## 7. Ticket en PDF

En el detalle de la incidencia están los botones **Ver ticket (PDF)** y **Descargar ticket**. El ticket contiene:

- Encabezado con los logotipos del **Gobierno del Estado de México** y del **TESCHI**, datos de la institución,
  **No. de ticket**, folio, fecha y hora.
- Nombre del docente o solicitante, carrera, No. de empleado o matrícula, teléfono y correo.
- Solicitud, categoría, prioridad, ubicación y descripción breve.
- **Tiempo estimado de atención** y fecha límite.
- Estado, responsable, evidencias y fecha de cierre al momento de imprimir.
- Leyenda *"Conserve este ticket para garantía"*, firmas del Departamento y de la persona atendida,
  espacio para el sello y *"Gracias por su visita"*.

![Ticket en PDF](img/16_ticket_pdf.png)

## 8. Atender incidencias asignadas

*(Docente, Administrativo, Coordinador y Administrador)*

Menú **Asignadas a mí**: las incidencias en las que eres responsable, ordenadas por prioridad y antigüedad.
Por defecto se ocultan las terminadas; usa **Incluir terminadas** para verlas.

![Asignadas a mí](img/07_asignadas.png)

En el detalle aparece el recuadro **Atender incidencia**:

1. Cambia el estado a **En proceso** cuando empieces a trabajar.
2. Cámbialo a **Resuelta** cuando termines. Es obligatorio **describir la solución** en el comentario.
3. Puedes adjuntar evidencias (por ejemplo, una foto del equipo reparado).

![Atender incidencia](img/08_atender_incidencia.png)

## 9. Gestionar incidencias (Coordinador y Administrador)

### 9.1 Todas las incidencias

Lista completa con filtros por **estado, categoría, prioridad, responsable** (incluye "Sin asignar") y búsqueda por
folio o título. Los filtros se conservan al cambiar de página.

![Todas las incidencias](img/09_todas_incidencias.png)

### 9.2 Gestionar una incidencia

En el detalle aparece el recuadro **Gestionar incidencia**. En un solo guardado puedes:

- Cambiar el **estado**.
- **Reclasificar**: corregir la **categoría** y la **prioridad** (queda en el seguimiento).
- Asignar o cambiar el **responsable**. Si la incidencia estaba *Pendiente* o *En revisión*, pasa sola a **Asignada**.
- Agregar un **comentario** y **evidencias**.

Quien reportó y el responsable reciben **una sola notificación** con todos los cambios.

## 10. Reportes

*(Coordinador y Administrador)* Menú **Reportes**.

- **Filtros**: rango de fechas, categoría, prioridad y periodos rápidos (este mes, últimos 3 meses, este año).
- **Indicadores**: registradas, abiertas, atendidas (%), canceladas, abiertas sin responsable, abiertas con más de
  7 días, tiempo promedio para asignar y tiempo promedio de resolución.
- **Gráficas**: por estado, prioridad, categoría, ubicación, **carrera** y por mes.
- **Tablas**: desempeño por responsable y abiertas más antiguas.
- **Exportar a Excel (CSV)** e **Imprimir / Guardar PDF**.

![Reportes](img/11_reportes.png)

## 11. Notificaciones

La campana muestra cuántos avisos tienes sin leer y se actualiza sola cada minuto.

| Evento | Quién recibe el aviso |
|---|---|
| Nueva incidencia | Coordinadores y Administradores |
| Te asignan (o te quitan) una incidencia | El responsable nuevo (y el anterior) |
| Cambio de estado, categoría o prioridad | Quien reportó y el responsable |
| Pasa a *Resuelta* o *Cancelada* | Además, Coordinadores y Administradores |
| Comentario o evidencia | Quien reportó y el responsable (si comenta quien reportó y aún no hay responsable, también Coordinadores y Administradores) |
| Solicitud de restablecer contraseña | Administradores |

Si el Departamento tiene configurado el correo, **cada aviso llega también a tu correo institucional** con un botón
para abrir la incidencia, y al registrar una incidencia recibes tu **ticket en PDF adjunto**. Revisa la carpeta de *spam*
la primera vez.

Nadie recibe avisos de sus propias acciones. En la página **Notificaciones** puedes filtrarlas (Todas / Sin leer),
marcarlas como leídas y eliminar las ya leídas.

![Notificaciones](img/10_notificaciones.png)

## 12. Mi perfil

Consulta tus datos, actualiza tu **carrera** y **teléfono** (aparecen en el ticket) y cambia tu **contraseña**
(debes escribir la actual). Si tu nombre, correo o matrícula son incorrectos, solicita el cambio al Administrador.

![Mi perfil](img/15_perfil.png)

## 13. Administración de usuarios

*(Administrador)* Menú **Usuarios**.

- **Buscar** por nombre, correo o matrícula y filtrar por rol o estado.
- **Nuevo usuario / Editar**: nombre, apellidos, matrícula o No. de empleado, correo, teléfono, rol, departamento,
  carrera y contraseña. El correo y la matrícula no se pueden repetir.
- **Desactivar / Activar**: los usuarios no se eliminan (tienen incidencias relacionadas); uno desactivado ya no puede
  iniciar sesión. No puedes desactivarte ni quitarte el rol de Administrador a ti mismo.

![Usuarios](img/12_usuarios.png)

Al pulsar **Editar** se abre el formulario del usuario:

![Editar usuario](img/21_editar_usuario.png)

### Restablecer la contraseña de un usuario

En **Editar usuario**, la sección **Restablecer contraseña** indica si el usuario lo solicitó. Pulsa
**Generar enlace**, copia el enlace con **Copiar** y entrégaselo al usuario (es válido 24 horas y una sola vez).
Si el correo está configurado, también puedes usar **Enviar enlace por correo**. Tú nunca conoces la nueva contraseña.

![Restablecer contraseña](img/13_restablecer_password.png)

## 14. Catálogos

*(Administrador)* Menú **Catálogos**, con dos pestañas:

- **Categorías**: nombre y descripción.
- **Prioridades**: nombre, **nivel** (mayor = más urgente) y **tiempo estimado de atención** en días hábiles.

Lo que ya usan incidencias no se puede eliminar; se **desactiva** y deja de aparecer al registrar incidencias nuevas.
Siempre debe quedar al menos un elemento activo. Los estados y roles no se editan porque el flujo y los permisos dependen
de ellos.

![Catálogos](img/14_catalogos.png)

## 15. Estados de una incidencia

| Estado | Significado | Quién lo asigna |
|---|---|---|
| **Pendiente** | Recién registrada | Automático al registrar |
| **En revisión** | El Departamento la está analizando | Coordinador / Administrador |
| **Asignada** | Tiene responsable | Automático al asignar responsable, o Coordinador |
| **En proceso** | El responsable la está atendiendo | Responsable o Coordinador |
| **Resuelta** | Se aplicó la solución (queda fecha de cierre) | Responsable o Coordinador |
| **Cerrada** | El Departamento la dio por terminada; ya no admite comentarios | Coordinador / Administrador |
| **Cancelada** | Ya no procede; ya no admite comentarios | Quien la reportó (si estaba *Pendiente*) o Coordinador |

Una incidencia *Cerrada* o *Cancelada* puede **reabrirse** por un Coordinador o Administrador.

## 16. Preguntas frecuentes

**No puedo iniciar sesión.** Revisa que el correo esté bien escrito y que no tengas activado el bloqueo de mayúsculas.
Si olvidaste la contraseña usa **¿Olvidaste tu contraseña?**. Si tu cuenta fue desactivada, pide al Administrador que la
active.

**No me llega el correo de recuperación.** Revisa la carpeta de *spam*. Si el sistema no tiene correo configurado,
el Administrador recibe el aviso y te entrega el enlace.

**No veo el menú «Todas las incidencias» o «Reportes».** Son módulos de Coordinador y Administrador. El menú solo
muestra lo que tu rol puede usar.

**No me deja adjuntar un archivo.** Solo se aceptan JPG, PNG, WEBP y PDF de hasta 5 MB, máximo 5 archivos por envío.
Si el archivo pesa más, comprímelo o elige otro.

**Cerré una incidencia por error.** Pide a un Coordinador o Administrador que la **reabra** cambiando su estado.

**¿Puedo editar el título o la descripción de una incidencia ya registrada?** No. Agrega un **comentario** con la
corrección; queda registrado en el seguimiento.

**¿Dónde veo la fecha límite de atención?** En el detalle de la incidencia (*Tiempo estimado de atención*) y en el
ticket en PDF.

**La página se ve rara después de una actualización del sistema.** Recarga con **Ctrl + F5** para que el navegador
descargue los estilos nuevos.

## Uso en celular

Todas las pantallas se adaptan al celular: el menú se acomoda en varias líneas, las tarjetas se apilan y las tablas se
desplazan de lado.

![Vista en celular](img/17_vista_celular.png)

![Dashboard en celular](img/22_celular_dashboard.png)
