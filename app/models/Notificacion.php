<?php

class Notificacion
{
    private $conn;
    private $table = "notificaciones";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function crear($usuario_id, $actor_id, $incidencia_id, $tipo, $mensaje)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO notificaciones
            (usuario_id, actor_id, incidencia_id, tipo, mensaje)
            VALUES
            (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $usuario_id,
            $actor_id,
            $incidencia_id,
            $tipo,
            mb_substr($mensaje, 0, 500)
        ]);
    }

    public function contarNoLeidas($usuario_id)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM notificaciones
            WHERE usuario_id = ?
            AND leida = 0
        ");

        $stmt->execute([$usuario_id]);

        return (int) $stmt->fetchColumn();
    }

    /*
     * Notificaciones del usuario, de la más reciente a la más antigua.
     */
    public function contar($usuario_id, $soloNoLeidas = false)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM notificaciones
            WHERE usuario_id = ?
            " . ($soloNoLeidas ? "AND leida = 0" : "") . "
        ");

        $stmt->execute([$usuario_id]);

        return (int) $stmt->fetchColumn();
    }

    public function listar($usuario_id, $soloNoLeidas = false, $limite = 100, $offset = 0)
    {
        $sql = "
            SELECT
                n.id,
                n.incidencia_id,
                n.actor_id,
                n.tipo,
                n.mensaje,
                n.leida,
                n.fecha,
                i.folio,
                i.titulo,
                CONCAT(a.nombre, ' ', COALESCE(a.apellido_paterno, '')) AS actor
            FROM notificaciones n
            LEFT JOIN incidencias i
                ON n.incidencia_id = i.id
            LEFT JOIN usuarios a
                ON n.actor_id = a.id
            WHERE n.usuario_id = ?
            " . ($soloNoLeidas ? "AND n.leida = 0" : "") . "
            ORDER BY n.fecha DESC, n.id DESC
            LIMIT " . (int) $limite . " OFFSET " . (int) $offset . "
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$usuario_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarLeida($id, $usuario_id)
    {
        $stmt = $this->conn->prepare("
            UPDATE notificaciones
            SET leida = 1, fecha_lectura = NOW()
            WHERE id = ?
            AND usuario_id = ?
            AND leida = 0
        ");

        $stmt->execute([$id, $usuario_id]);
    }

    /*
     * Al abrir una incidencia se dan por leídos sus avisos.
     */
    public function marcarLeidasDeIncidencia($incidencia_id, $usuario_id)
    {
        $stmt = $this->conn->prepare("
            UPDATE notificaciones
            SET leida = 1, fecha_lectura = NOW()
            WHERE incidencia_id = ?
            AND usuario_id = ?
            AND leida = 0
        ");

        $stmt->execute([$incidencia_id, $usuario_id]);
    }

    public function marcarTodasLeidas($usuario_id)
    {
        $stmt = $this->conn->prepare("
            UPDATE notificaciones
            SET leida = 1, fecha_lectura = NOW()
            WHERE usuario_id = ?
            AND leida = 0
        ");

        $stmt->execute([$usuario_id]);

        return $stmt->rowCount();
    }

    public function eliminarLeidas($usuario_id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM notificaciones
            WHERE usuario_id = ?
            AND leida = 1
        ");

        $stmt->execute([$usuario_id]);

        return $stmt->rowCount();
    }
}
