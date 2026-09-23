<?php

class Incidencia
{
    /*
     * Estados que dan por terminada la incidencia:
     * al llegar a ellos se guarda la fecha de cierre.
     */
    const ESTADOS_FINALES = ["Resuelta", "Cerrada", "Cancelada"];

    /*
     * Estados en los que ya no se aceptan comentarios
     * (un gestor puede reabrir la incidencia).
     */
    const ESTADOS_BLOQUEADOS = ["Cerrada", "Cancelada"];

    /*
     * Estados que puede elegir un responsable que no es gestor.
     */
    const ESTADOS_RESPONSABLE = ["En proceso", "Resuelta"];

    /*
     * Roles que pueden ser asignados como responsables.
     */
    const ROLES_RESPONSABLES = ["Administrador", "Coordinador", "Docente", "Administrativo"];

    private $conn;
    private $table = "incidencias";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function buscarPorId($id)
    {
        $sql = "
            SELECT
                i.*,

                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.correo,

                CONCAT(r.nombre, ' ', COALESCE(r.apellido_paterno, '')) AS responsable,

                c.nombre AS categoria,
                p.nombre AS prioridad,
                e.nombre AS estado

            FROM incidencias i

            INNER JOIN usuarios u
                ON i.usuario_id = u.id

            LEFT JOIN usuarios r
                ON i.responsable_id = r.id

            INNER JOIN categorias c
                ON i.categoria_id = c.id

            INNER JOIN prioridades p
                ON i.prioridad_id = p.id

            INNER JOIN estados_incidencia e
                ON i.estado_id = e.id

            WHERE i.id = ?

            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerEstados()
    {
        return $this->conn->query("
            SELECT id, nombre
            FROM estados_incidencia
            ORDER BY id
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /*
     * Usuarios activos que pueden atender incidencias.
     */
    public function obtenerResponsables()
    {
        $marcadores = implode(", ", array_fill(0, count(self::ROLES_RESPONSABLES), "?"));

        $stmt = $this->conn->prepare("
            SELECT
                u.id,
                CONCAT(u.nombre, ' ', COALESCE(u.apellido_paterno, ''), ' (', r.nombre, ')') AS nombre
            FROM usuarios u
            INNER JOIN roles r
                ON u.rol_id = r.id
            WHERE u.activo = 1
            AND r.nombre IN ($marcadores)
            ORDER BY u.nombre, u.apellido_paterno
        ");

        $stmt->execute(self::ROLES_RESPONSABLES);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function registrarHistorial(
        $incidencia_id,
        $usuario_id,
        $accion,
        $descripcion,
        $estado_anterior_id = null,
        $estado_nuevo_id = null
    ) {
        $stmt = $this->conn->prepare("
            INSERT INTO historial_incidencias
            (
                incidencia_id,
                usuario_id,
                accion,
                estado_anterior_id,
                estado_nuevo_id,
                descripcion
            )
            VALUES
            (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidencia_id,
            $usuario_id,
            $accion,
            $estado_anterior_id,
            $estado_nuevo_id,
            $descripcion
        ]);
    }

    /*
     * Cambia el estado, maneja la fecha de cierre y deja registro
     * en el historial. $incidencia es el arreglo de buscarPorId()
     * y se actualiza para las siguientes acciones del mismo guardado.
     */
    public function cambiarEstado(&$incidencia, $estado_id, $usuario_id)
    {
        $estados = $this->obtenerEstados();

        if ((int) $incidencia["estado_id"] === (int) $estado_id) {
            return false;
        }

        $nuevoEstado = $estados[$estado_id];

        $esFinal = in_array($nuevoEstado, self::ESTADOS_FINALES, true);

        /*
         * Si el nuevo estado es final se guarda la fecha de cierre
         * (solo la primera vez); si se reabre, se limpia.
         */
        $stmt = $this->conn->prepare("
            UPDATE incidencias
            SET
                estado_id = ?,
                fecha_cierre = " . ($esFinal ? "COALESCE(fecha_cierre, NOW())" : "NULL") . "
            WHERE id = ?
        ");

        $stmt->execute([$estado_id, $incidencia["id"]]);

        $this->registrarHistorial(
            $incidencia["id"],
            $usuario_id,
            "estado",
            "Estado: " . $incidencia["estado"] . " → " . $nuevoEstado,
            $incidencia["estado_id"],
            $estado_id
        );

        $incidencia["estado_id"] = $estado_id;
        $incidencia["estado"] = $nuevoEstado;

        /*
         * Avisan: quien reportó y el responsable. Si queda Resuelta
         * (hay que revisarla y cerrarla) o Cancelada, también los gestores.
         */
        $destinatarios = [$incidencia["usuario_id"], $incidencia["responsable_id"]];

        if (in_array($nuevoEstado, ["Resuelta", "Cancelada"], true)) {
            $destinatarios = array_merge($destinatarios, $this->obtenerGestores());
        }

        $this->avisar($destinatarios, "estado", "cambió el estado a " . $nuevoEstado, $usuario_id);

        return true;
    }

    /*
     * Asigna (o quita, con null) el responsable de la incidencia.
     */
    public function asignarResponsable(&$incidencia, $responsable_id, $usuario_id)
    {
        $actual = $incidencia["responsable_id"] !== null ? (int) $incidencia["responsable_id"] : null;
        $nuevo = $responsable_id !== null ? (int) $responsable_id : null;

        if ($actual === $nuevo) {
            return false;
        }

        $stmt = $this->conn->prepare("
            UPDATE incidencias
            SET responsable_id = ?
            WHERE id = ?
        ");

        $stmt->execute([$nuevo, $incidencia["id"]]);

        $nombreNuevo = $nuevo !== null ? $this->nombreUsuario($nuevo) : null;

        if ($nuevo === null) {
            $descripcion = "Se quitó al responsable " . trim($incidencia["responsable"]);
        } else {
            $descripcion = "Responsable asignado: " . $nombreNuevo;
        }

        $this->registrarHistorial($incidencia["id"], $usuario_id, "asignacion", $descripcion);

        $this->avisar([$actual], "asignacion", "te quitó como responsable", $usuario_id);
        $this->avisar([$nuevo], "asignacion", "te asignó esta incidencia", $usuario_id);

        $incidencia["responsable_id"] = $nuevo;
        $incidencia["responsable"] = $nombreNuevo;

        return true;
    }

    public function agregarComentario($incidencia, $usuario_id, $comentario)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO comentarios_incidencia
            (incidencia_id, usuario_id, comentario)
            VALUES
            (?, ?, ?)
        ");

        $stmt->execute([$incidencia["id"], $usuario_id, $comentario]);

        /*
         * Un comentario también cuenta como actividad de la incidencia.
         */
        $this->conn
            ->prepare("UPDATE incidencias SET fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$incidencia["id"]]);

        /*
         * Avisan: quien reportó y el responsable. Si comenta quien reportó
         * y aún no hay responsable, se avisa a los gestores para que la atiendan.
         */
        $destinatarios = [$incidencia["usuario_id"], $incidencia["responsable_id"]];

        if (
            (int) $usuario_id === (int) $incidencia["usuario_id"] &&
            $incidencia["responsable_id"] === null
        ) {
            $destinatarios = array_merge($destinatarios, $this->obtenerGestores());
        }

        $extracto = mb_strlen($comentario) > 100
            ? mb_substr($comentario, 0, 100) . "…"
            : $comentario;

        $this->avisar($destinatarios, "comentario", "comentó: “" . $extracto . "”", $usuario_id);
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICACIONES
    |--------------------------------------------------------------------------
    | Las acciones anteriores solo acumulan avisos; enviarAvisos() los
    | guarda al final, uno por destinatario, aunque en un mismo guardado
    | haya varios cambios (p. ej. asignar + cambiar estado + comentar).
    */

    private $avisos = [];

    /*
     * Ids de Administradores y Coordinadores activos.
     */
    public function obtenerGestores()
    {
        return $this->conn->query("
            SELECT u.id
            FROM usuarios u
            INNER JOIN roles r
                ON u.rol_id = r.id
            WHERE u.activo = 1
            AND r.nombre IN ('Administrador', 'Coordinador')
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    /*
     * Acumula un aviso para cada usuario indicado, excepto quien hizo
     * la acción (nadie recibe avisos de lo que él mismo hizo).
     */
    public function avisar(array $usuarios, $tipo, $frase, $actor_id)
    {
        foreach ($usuarios as $usuario) {

            if ($usuario === null || (int) $usuario === (int) $actor_id) {
                continue;
            }

            $usuario = (int) $usuario;

            $this->avisos[$usuario]["tipos"][] = $tipo;

            if (!in_array($frase, $this->avisos[$usuario]["frases"] ?? [], true)) {
                $this->avisos[$usuario]["frases"][] = $frase;
            }
        }
    }

    /*
     * Guarda las notificaciones acumuladas. Ejemplo de mensaje:
     * "Luis Perez te asignó esta incidencia, cambió el estado a Asignada
     *  y comentó: “Lo revisa Pedro”"
     */
    public function enviarAvisos($incidencia_id, $actor_id)
    {
        if (empty($this->avisos)) {
            return 0;
        }

        require_once __DIR__ . "/Notificacion.php";

        $notificacionModel = new Notificacion($this->conn);

        $actor = $this->nombreUsuario($actor_id);

        foreach ($this->avisos as $usuario => $aviso) {

            $tipos = array_unique($aviso["tipos"]);
            $frases = $aviso["frases"];

            $ultima = array_pop($frases);

            $texto = $frases ? implode(", ", $frases) . " y " . $ultima : $ultima;

            $notificacionModel->crear(
                $usuario,
                $actor_id,
                $incidencia_id,
                count($tipos) === 1 ? reset($tipos) : "actualizacion",
                $actor . " " . $texto
            );
        }

        $enviados = count($this->avisos);

        $this->avisos = [];

        return $enviados;
    }

    private function nombreUsuario($id)
    {
        $stmt = $this->conn->prepare("
            SELECT CONCAT(nombre, ' ', COALESCE(apellido_paterno, ''))
            FROM usuarios
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return trim((string) $stmt->fetchColumn());
    }

    /*
     * Línea de tiempo: historial y comentarios juntos, del más antiguo
     * al más reciente.
     */
    public function obtenerSeguimiento($incidencia_id)
    {
        $sql = "
            SELECT
                'historial' AS tipo,
                h.accion,
                h.descripcion AS texto,
                h.fecha,
                h.id,
                CONCAT(u.nombre, ' ', COALESCE(u.apellido_paterno, '')) AS usuario,
                r.nombre AS rol
            FROM historial_incidencias h
            INNER JOIN usuarios u ON h.usuario_id = u.id
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE h.incidencia_id = ?

            UNION ALL

            SELECT
                'comentario' AS tipo,
                NULL AS accion,
                c.comentario AS texto,
                c.fecha,
                c.id,
                CONCAT(u.nombre, ' ', COALESCE(u.apellido_paterno, '')) AS usuario,
                r.nombre AS rol
            FROM comentarios_incidencia c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE c.incidencia_id = ?

            ORDER BY fecha, tipo DESC, id
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$incidencia_id, $incidencia_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
