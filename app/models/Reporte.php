<?php

require_once __DIR__ . "/Incidencia.php";

/*
|--------------------------------------------------------------------------
| CONSULTAS ESTADÍSTICAS (sobre la colección "incidencias" de MongoDB)
|--------------------------------------------------------------------------
| Todas reciben los mismos filtros:
|   desde, hasta   -> fechas (Y-m-d) sobre la fecha de registro
|   categoria_id, prioridad_id
|
| "Atendidas" = incidencias en estado Resuelta o Cerrada.
| "Abiertas"  = cualquier estado que no sea Resuelta, Cerrada o Cancelada.
|
| Como el volumen de una área es pequeño, se cargan las incidencias que pasan
| el filtro y las estadísticas se calculan en PHP (equivale a los GROUP BY /
| TIMESTAMPDIFF de SQL, con los mismos resultados).
*/

class Reporte
{
    const ESTADOS_ATENDIDAS = ["Resuelta", "Cerrada"];
    const ESTADOS_TERMINADAS = ["Resuelta", "Cerrada", "Cancelada"];

    private $db;
    private $filtro = [];

    private $docsCache = null;
    private $catCache = null;
    private $priCache = null;
    private $rolCache = null;
    private $usuCache = null;

    public function __construct($db, array $filtros)
    {
        $this->db = $db;

        if (!empty($filtros["desde"])) {
            $this->filtro["fecha_registro"]['$gte'] = $filtros["desde"] . " 00:00:00";
        }

        if (!empty($filtros["hasta"])) {
            // "hasta" incluye todo ese día.
            $siguiente = date("Y-m-d", strtotime($filtros["hasta"] . " +1 day"));
            $this->filtro["fecha_registro"]['$lt'] = $siguiente . " 00:00:00";
        }

        if (!empty($filtros["categoria_id"])) {
            $this->filtro["categoria_id"] = (int) $filtros["categoria_id"];
        }

        if (!empty($filtros["prioridad_id"])) {
            $this->filtro["prioridad_id"] = (int) $filtros["prioridad_id"];
        }
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
            "prioridad_id" => isset($prioridades[$get["prioridad_id"] ?? ""]) ? $get["prioridad_id"] : "",
        ];

        if ($filtros["desde"] && $filtros["hasta"] && $filtros["desde"] > $filtros["hasta"]) {
            [$filtros["desde"], $filtros["hasta"]] = [$filtros["hasta"], $filtros["desde"]];
        }

        return $filtros;
    }

    /*
     * Incidencias que pasan el filtro (sin comentarios ni evidencias).
     */
    private function docs()
    {
        if ($this->docsCache === null) {
            $this->docsCache = $this->db->getCollection("incidencias")->find(
                $this->filtro,
                ["projection" => ["comentarios" => 0, "evidencias" => 0]]
            )->toArray();
        }

        return $this->docsCache;
    }

    private function categorias()
    {
        if ($this->catCache === null) {
            $this->catCache = [];
            foreach ($this->db->getCollection("categorias")->find([], ["sort" => ["nombre" => 1]]) as $c) {
                $this->catCache[(int) $c["_id"]] = $c["nombre"];
            }
        }
        return $this->catCache;
    }

    private function prioridades()
    {
        if ($this->priCache === null) {
            $this->priCache = [];
            foreach ($this->db->getCollection("prioridades")->find([], ["sort" => ["nivel" => 1]]) as $p) {
                $this->priCache[(int) $p["_id"]] = $p["nombre"];
            }
        }
        return $this->priCache;
    }

    private function roles()
    {
        if ($this->rolCache === null) {
            $this->rolCache = [];
            foreach ($this->db->getCollection("roles")->find() as $r) {
                $this->rolCache[(int) $r["_id"]] = $r["nombre"];
            }
        }
        return $this->rolCache;
    }

    private function usuario($id)
    {
        if ($id === null) {
            return null;
        }
        $id = (int) $id;
        if (!isset($this->usuCache[$id])) {
            $this->usuCache[$id] = $this->db->getCollection("usuarios")->findOne(["_id" => $id]) ?: false;
        }
        return $this->usuCache[$id] ?: null;
    }

    private static function nombre($u)
    {
        return $u ? trim($u["nombre"] . " " . ($u["apellido_paterno"] ?? "")) : "";
    }

    private static function nombreCompleto($u)
    {
        return $u ? trim($u["nombre"] . " " . ($u["apellido_paterno"] ?? "") . " " . ($u["apellido_materno"] ?? "")) : "";
    }

    private static function horas($desde, $hasta)
    {
        if (!$desde || !$hasta) {
            return null;
        }
        return (strtotime($hasta) - strtotime($desde)) / 3600;
    }

    private static function promedio(array $valores)
    {
        $valores = array_filter($valores, fn($v) => $v !== null);
        return $valores ? array_sum($valores) / count($valores) : null;
    }

    /*
     * Momento de la primera asignación (para el tiempo hasta asignar).
     */
    private static function fechaAsignacion($doc)
    {
        foreach ($doc["historial"] ?? [] as $h) {
            if (($h["accion"] ?? "") === "asignacion") {
                return $h["fecha"];
            }
        }
        return null;
    }

    /*
     * Indicadores generales. Los tiempos se devuelven en horas.
     */
    public function resumen()
    {
        $docs = $this->docs();

        $total = count($docs);
        $abiertas = $atendidas = $canceladas = $sin_asignar = $atrasadas = 0;
        $tiemposResolucion = [];
        $tiemposAsignacion = [];

        $limite7dias = date("Y-m-d H:i:s", strtotime("-7 days"));

        foreach ($docs as $d) {

            $estado = $d["estado"];
            $terminada = in_array($estado, self::ESTADOS_TERMINADAS, true);
            $atendida = in_array($estado, self::ESTADOS_ATENDIDAS, true);

            if (!$terminada) {
                $abiertas++;
                if (($d["responsable_id"] ?? null) === null) {
                    $sin_asignar++;
                }
                if ($d["fecha_registro"] < $limite7dias) {
                    $atrasadas++;
                }
            }

            if ($atendida) {
                $atendidas++;
                if (!empty($d["fecha_cierre"])) {
                    $tiemposResolucion[] = self::horas($d["fecha_registro"], $d["fecha_cierre"]);
                }
            }

            if ($estado === "Cancelada") {
                $canceladas++;
            }

            $asignacion = self::fechaAsignacion($d);
            if ($asignacion) {
                $tiemposAsignacion[] = self::horas($d["fecha_registro"], $asignacion);
            }
        }

        return [
            "total" => $total,
            "abiertas" => $abiertas,
            "atendidas" => $atendidas,
            "canceladas" => $canceladas,
            "sin_asignar" => $sin_asignar,
            "atrasadas" => $atrasadas,
            "horas_resolucion" => self::promedio($tiemposResolucion),
            "horas_asignacion" => self::promedio($tiemposAsignacion),
        ];
    }

    public function porEstado()
    {
        $conteo = array_fill_keys(array_values(Incidencia::ESTADOS), 0);

        foreach ($this->docs() as $d) {
            if (isset($conteo[$d["estado"]])) {
                $conteo[$d["estado"]]++;
            }
        }

        $filas = [];
        foreach (Incidencia::ESTADOS as $nombre) {
            $filas[] = ["nombre" => $nombre, "total" => $conteo[$nombre]];
        }

        return $filas;
    }

    public function porCategoria()
    {
        $categorias = $this->categorias();
        $conteo = array_fill_keys(array_keys($categorias), 0);

        foreach ($this->docs() as $d) {
            $id = (int) $d["categoria_id"];
            if (isset($conteo[$id])) {
                $conteo[$id]++;
            }
        }

        $filas = [];
        foreach ($categorias as $id => $nombre) {
            $filas[] = ["nombre" => $nombre, "total" => $conteo[$id]];
        }

        // Orden: total DESC, nombre.
        usort($filas, fn($a, $b) => [$b["total"], $a["nombre"]] <=> [$a["total"], $b["nombre"]]);

        return $filas;
    }

    public function porPrioridad()
    {
        // Ya vienen ordenadas por nivel.
        $prioridades = $this->prioridades();
        $conteo = array_fill_keys(array_keys($prioridades), 0);

        foreach ($this->docs() as $d) {
            $id = (int) $d["prioridad_id"];
            if (isset($conteo[$id])) {
                $conteo[$id]++;
            }
        }

        $filas = [];
        foreach ($prioridades as $id => $nombre) {
            $filas[] = ["nombre" => $nombre, "total" => $conteo[$id]];
        }

        return $filas;
    }

    /*
     * Registradas por mes y cuántas de ellas ya fueron atendidas.
     */
    public function porMes()
    {
        $meses = [];

        foreach ($this->docs() as $d) {
            $mes = substr($d["fecha_registro"], 0, 7); // Y-m

            if (!isset($meses[$mes])) {
                $meses[$mes] = ["mes" => $mes, "registradas" => 0, "atendidas" => 0];
            }

            $meses[$mes]["registradas"]++;

            if (in_array($d["estado"], self::ESTADOS_ATENDIDAS, true)) {
                $meses[$mes]["atendidas"]++;
            }
        }

        ksort($meses);

        return array_values($meses);
    }

    /*
     * Desempeño de cada responsable.
     */
    public function porResponsable()
    {
        $roles = $this->roles();
        $grupos = [];

        foreach ($this->docs() as $d) {

            $rid = $d["responsable_id"] ?? null;
            if ($rid === null) {
                continue;
            }
            $rid = (int) $rid;

            if (!isset($grupos[$rid])) {
                $u = $this->usuario($rid);
                $grupos[$rid] = [
                    "responsable" => self::nombre($u),
                    "rol" => $roles[(int) ($u["rol_id"] ?? 0)] ?? "",
                    "asignadas" => 0,
                    "abiertas" => 0,
                    "atendidas" => 0,
                    "_tiempos" => [],
                ];
            }

            $grupos[$rid]["asignadas"]++;

            if (!in_array($d["estado"], self::ESTADOS_TERMINADAS, true)) {
                $grupos[$rid]["abiertas"]++;
            }

            if (in_array($d["estado"], self::ESTADOS_ATENDIDAS, true)) {
                $grupos[$rid]["atendidas"]++;
                if (!empty($d["fecha_cierre"])) {
                    $grupos[$rid]["_tiempos"][] = self::horas($d["fecha_registro"], $d["fecha_cierre"]);
                }
            }
        }

        $filas = [];
        foreach ($grupos as $g) {
            $g["horas_resolucion"] = self::promedio($g["_tiempos"]);
            unset($g["_tiempos"]);
            $filas[] = $g;
        }

        usort($filas, fn($a, $b) => [$b["asignadas"], $a["responsable"]] <=> [$a["asignadas"], $b["responsable"]]);

        return $filas;
    }

    /*
     * Incidencias por carrera (la capturada al registrar).
     */
    public function porCarrera()
    {
        $conteo = [];

        foreach ($this->docs() as $d) {
            $carrera = trim((string) ($d["carrera"] ?? ""));
            $carrera = $carrera !== "" ? $carrera : "Sin carrera / No aplica";
            $conteo[$carrera] = ($conteo[$carrera] ?? 0) + 1;
        }

        $filas = [];
        foreach ($conteo as $carrera => $total) {
            $filas[] = ["carrera" => $carrera, "total" => $total];
        }

        usort($filas, fn($a, $b) => [$b["total"], $a["carrera"]] <=> [$a["total"], $b["carrera"]]);

        return $filas;
    }

    /*
     * Ubicaciones con más incidencias.
     */
    public function porUbicacion($limite = 10)
    {
        $conteo = [];

        foreach ($this->docs() as $d) {
            $ubic = trim((string) ($d["ubicacion"] ?? ""));
            $ubic = $ubic !== "" ? $ubic : "No especificada";
            $conteo[$ubic] = ($conteo[$ubic] ?? 0) + 1;
        }

        $filas = [];
        foreach ($conteo as $ubic => $total) {
            $filas[] = ["ubicacion" => $ubic, "total" => $total];
        }

        usort($filas, fn($a, $b) => [$b["total"], $a["ubicacion"]] <=> [$a["total"], $b["ubicacion"]]);

        return array_slice($filas, 0, (int) $limite);
    }

    /*
     * Incidencias abiertas más antiguas (las que requieren atención).
     */
    public function abiertasMasAntiguas($limite = 10)
    {
        $prioridades = $this->prioridades();

        $abiertas = [];

        foreach ($this->docs() as $d) {
            if (in_array($d["estado"], self::ESTADOS_TERMINADAS, true)) {
                continue;
            }
            $abiertas[] = $d;
        }

        usort($abiertas, fn($a, $b) => $a["fecha_registro"] <=> $b["fecha_registro"]);

        $abiertas = array_slice($abiertas, 0, (int) $limite);

        $filas = [];
        foreach ($abiertas as $d) {
            $filas[] = [
                "id" => (int) $d["_id"],
                "folio" => $d["folio"],
                "titulo" => $d["titulo"],
                "fecha_registro" => $d["fecha_registro"],
                "dias" => (int) floor((time() - strtotime($d["fecha_registro"])) / 86400),
                "prioridad" => $prioridades[(int) $d["prioridad_id"]] ?? "",
                "estado" => $d["estado"],
                "responsable" => self::nombre($this->usuario($d["responsable_id"] ?? null)),
            ];
        }

        return $filas;
    }

    /*
     * Listado completo para exportar a CSV.
     */
    public function listadoDetallado()
    {
        $categorias = $this->categorias();
        $prioridades = $this->prioridades();

        $docs = $this->docs();

        usort($docs, fn($a, $b) => $b["fecha_registro"] <=> $a["fecha_registro"]);

        $filas = [];

        foreach ($docs as $d) {

            $reporta = $this->usuario($d["usuario_id"]);

            $filas[] = [
                "folio" => $d["folio"],
                "titulo" => $d["titulo"],
                "categoria" => $categorias[(int) $d["categoria_id"]] ?? "",
                "prioridad" => $prioridades[(int) $d["prioridad_id"]] ?? "",
                "estado" => $d["estado"],
                "reporto" => self::nombreCompleto($reporta),
                "correo" => $reporta["correo"] ?? "",
                "carrera" => $d["carrera"] ?? "",
                "telefono_contacto" => $d["telefono_contacto"] ?? "",
                "responsable" => self::nombre($this->usuario($d["responsable_id"] ?? null)),
                "ubicacion" => $d["ubicacion"] ?? "",
                "fecha_registro" => $d["fecha_registro"],
                "fecha_cierre" => $d["fecha_cierre"] ?? null,
                "horas_resolucion" => !empty($d["fecha_cierre"])
                    ? round(self::horas($d["fecha_registro"], $d["fecha_cierre"]), 1)
                    : null,
                "descripcion" => $d["descripcion"],
            ];
        }

        return $filas;
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
