<?php

/*
|--------------------------------------------------------------------------
| CONSULTAS ESTADÍSTICAS
|--------------------------------------------------------------------------
| Todas las consultas reciben los mismos filtros:
|   desde, hasta   -> fechas (Y-m-d) sobre la fecha de registro
|   categoria_id, prioridad_id
|
| "Atendidas" = incidencias en estado Resuelta o Cerrada.
| "Abiertas"  = cualquier estado que no sea Resuelta, Cerrada o Cancelada.
*/

class Reporte
{
    const ESTADOS_ATENDIDAS = "'Resuelta', 'Cerrada'";
    const ESTADOS_TERMINADAS = "'Resuelta', 'Cerrada', 'Cancelada'";

    private $conn;

    private $where = "";
    private $parametros = [];

    public function __construct($db, array $filtros)
    {
        $this->conn = $db;

        $condiciones = [];

        if (!empty($filtros["desde"])) {
            $condiciones[] = "i.fecha_registro >= ?";
            $this->parametros[] = $filtros["desde"];
        }

        if (!empty($filtros["hasta"])) {
            // "hasta" incluye todo ese día.
            $condiciones[] = "i.fecha_registro < DATE_ADD(?, INTERVAL 1 DAY)";
            $this->parametros[] = $filtros["hasta"];
        }

        if (!empty($filtros["categoria_id"])) {
            $condiciones[] = "i.categoria_id = ?";
            $this->parametros[] = $filtros["categoria_id"];
        }

        if (!empty($filtros["prioridad_id"])) {
            $condiciones[] = "i.prioridad_id = ?";
            $this->parametros[] = $filtros["prioridad_id"];
        }

        $this->where = $condiciones ? "WHERE " . implode(" AND ", $condiciones) : "";
    }

    /*
     * Lee y valida los filtros enviados por GET.
     */
    public static function filtrosDesdeRequest(array $get, array $categorias, array $prioridades)
    {
        $fecha = function ($valor) {
            $d = DateTime::createFromFormat("!Y-m-d", (string) $valor);
            return $d && $d->format("Y-m-d") === $valor ? $valor : "";
        };

        $filtros = [
            "desde" => $fecha($get["desde"] ?? ""),
            "hasta" => $fecha($get["hasta"] ?? ""),
            "categoria_id" => isset($categorias[$get["categoria_id"] ?? ""]) ? $get["categoria_id"] : "",
            "prioridad_id" => isset($prioridades[$get["prioridad_id"] ?? ""]) ? $get["prioridad_id"] : ""
        ];

        // Si las fechas vienen al revés, se intercambian.
        if ($filtros["desde"] && $filtros["hasta"] && $filtros["desde"] > $filtros["hasta"]) {
            [$filtros["desde"], $filtros["hasta"]] = [$filtros["hasta"], $filtros["desde"]];
        }

        return $filtros;
    }

    private function consultar($sql)
    {
        $stmt = $this->conn->prepare($sql);

        $stmt->execute($this->parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Indicadores generales. Los tiempos se devuelven en horas.
     */
    public function resumen()
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(e.nombre NOT IN (" . self::ESTADOS_TERMINADAS . ")) AS abiertas,
                SUM(e.nombre IN (" . self::ESTADOS_ATENDIDAS . ")) AS atendidas,
                SUM(e.nombre = 'Cancelada') AS canceladas,
                SUM(
                    e.nombre NOT IN (" . self::ESTADOS_TERMINADAS . ")
                    AND i.responsable_id IS NULL
                ) AS sin_asignar,
                SUM(
                    e.nombre NOT IN (" . self::ESTADOS_TERMINADAS . ")
                    AND i.fecha_registro < NOW() - INTERVAL 7 DAY
                ) AS atrasadas,
                AVG(
                    CASE WHEN e.nombre IN (" . self::ESTADOS_ATENDIDAS . ") AND i.fecha_cierre IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, i.fecha_registro, i.fecha_cierre) END
                ) / 60 AS horas_resolucion,
                AVG(
                    TIMESTAMPDIFF(MINUTE, i.fecha_registro, (
                        SELECT MIN(h.fecha)
                        FROM historial_incidencias h
                        WHERE h.incidencia_id = i.id
                        AND h.accion = 'asignacion'
                    ))
                ) / 60 AS horas_asignacion
            FROM incidencias i
            INNER JOIN estados_incidencia e
                ON i.estado_id = e.id
            {$this->where}
        ";

        $fila = $this->consultar($sql)[0];

        foreach (["total", "abiertas", "atendidas", "canceladas", "sin_asignar", "atrasadas"] as $campo) {
            $fila[$campo] = (int) $fila[$campo];
        }

        return $fila;
    }

    /*
     * Conteo agrupado por una tabla de catálogo. Incluye los valores
     * sin incidencias (en 0) para que la gráfica esté completa.
     */
    private function agruparPorCatalogo($tabla, $columna, $orden)
    {
        $sql = "
            SELECT
                x.nombre,
                COUNT(i.id) AS total
            FROM $tabla x
            LEFT JOIN (
                SELECT i.*
                FROM incidencias i
                {$this->where}
            ) i
                ON i.$columna = x.id
            GROUP BY x.id, x.nombre
            ORDER BY $orden
        ";

        return $this->consultar($sql);
    }

    public function porEstado()
    {
        return $this->agruparPorCatalogo("estados_incidencia", "estado_id", "x.id");
    }

    public function porCategoria()
    {
        return $this->agruparPorCatalogo("categorias", "categoria_id", "total DESC, x.nombre");
    }

    public function porPrioridad()
    {
        return $this->agruparPorCatalogo("prioridades", "prioridad_id", "x.nivel");
    }

    /*
     * Registradas por mes y cuántas de ellas ya fueron atendidas.
     */
    public function porMes()
    {
        $sql = "
            SELECT
                DATE_FORMAT(i.fecha_registro, '%Y-%m') AS mes,
                COUNT(*) AS registradas,
                SUM(e.nombre IN (" . self::ESTADOS_ATENDIDAS . ")) AS atendidas
            FROM incidencias i
            INNER JOIN estados_incidencia e
                ON i.estado_id = e.id
            {$this->where}
            GROUP BY mes
            ORDER BY mes
        ";

        return $this->consultar($sql);
    }

    /*
     * Desempeño de cada responsable.
     */
    public function porResponsable()
    {
        $sql = "
            SELECT
                CONCAT(u.nombre, ' ', COALESCE(u.apellido_paterno, '')) AS responsable,
                r.nombre AS rol,
                COUNT(*) AS asignadas,
                SUM(e.nombre NOT IN (" . self::ESTADOS_TERMINADAS . ")) AS abiertas,
                SUM(e.nombre IN (" . self::ESTADOS_ATENDIDAS . ")) AS atendidas,
                AVG(
                    CASE WHEN e.nombre IN (" . self::ESTADOS_ATENDIDAS . ") AND i.fecha_cierre IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, i.fecha_registro, i.fecha_cierre) END
                ) / 60 AS horas_resolucion
            FROM incidencias i
            INNER JOIN usuarios u
                ON i.responsable_id = u.id
            INNER JOIN roles r
                ON u.rol_id = r.id
            INNER JOIN estados_incidencia e
                ON i.estado_id = e.id
            {$this->where}
            GROUP BY u.id, u.nombre, u.apellido_paterno, r.nombre
            ORDER BY asignadas DESC, responsable
        ";

        return $this->consultar($sql);
    }

    /*
     * Ubicaciones con más incidencias.
     */
    public function porUbicacion($limite = 10)
    {
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(i.ubicacion), ''), 'No especificada') AS ubicacion,
                COUNT(*) AS total
            FROM incidencias i
            {$this->where}
            GROUP BY ubicacion
            ORDER BY total DESC, ubicacion
            LIMIT " . (int) $limite . "
        ";

        return $this->consultar($sql);
    }

    /*
     * Incidencias abiertas más antiguas (las que requieren atención).
     */
    public function abiertasMasAntiguas($limite = 10)
    {
        $sql = "
            SELECT
                i.id,
                i.folio,
                i.titulo,
                i.fecha_registro,
                TIMESTAMPDIFF(DAY, i.fecha_registro, NOW()) AS dias,
                p.nombre AS prioridad,
                e.nombre AS estado,
                CONCAT(r.nombre, ' ', COALESCE(r.apellido_paterno, '')) AS responsable
            FROM incidencias i
            INNER JOIN estados_incidencia e
                ON i.estado_id = e.id
            INNER JOIN prioridades p
                ON i.prioridad_id = p.id
            LEFT JOIN usuarios r
                ON i.responsable_id = r.id
            " . ($this->where ? $this->where . " AND" : "WHERE") . "
                e.nombre NOT IN (" . self::ESTADOS_TERMINADAS . ")
            ORDER BY i.fecha_registro
            LIMIT " . (int) $limite . "
        ";

        return $this->consultar($sql);
    }

    /*
     * Listado completo para exportar a CSV.
     */
    public function listadoDetallado()
    {
        $sql = "
            SELECT
                i.folio,
                i.titulo,
                c.nombre AS categoria,
                p.nombre AS prioridad,
                e.nombre AS estado,
                CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS reporto,
                u.correo,
                CONCAT_WS(' ', r.nombre, r.apellido_paterno) AS responsable,
                i.ubicacion,
                i.fecha_registro,
                i.fecha_cierre,
                ROUND(TIMESTAMPDIFF(MINUTE, i.fecha_registro, i.fecha_cierre) / 60, 1) AS horas_resolucion,
                i.descripcion
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
            {$this->where}
            ORDER BY i.fecha_registro DESC
        ";

        return $this->consultar($sql);
    }

    /*
     * Convierte horas a un texto legible: "45 min", "5.5 h", "3 d 4 h".
     */
    public static function formatearDuracion($horas)
    {
        if ($horas === null) {
            return "—";
        }

        $horas = (float) $horas;

        if ($horas < 1) {
            return round($horas * 60) . " min";
        }

        if ($horas < 24) {
            return round($horas, 1) . " h";
        }

        $dias = floor($horas / 24);
        $resto = round($horas - $dias * 24);

        if ($resto >= 24) {
            $dias++;
            $resto = 0;
        }

        return $dias . " d" . ($resto > 0 ? " " . $resto . " h" : "");
    }
}
