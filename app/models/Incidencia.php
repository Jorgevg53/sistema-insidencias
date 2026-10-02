<?php

/*
|--------------------------------------------------------------------------
| INCIDENCIAS (colección "incidencias" de MongoDB)
|--------------------------------------------------------------------------
| Cada incidencia es un documento que EMBEBE su historial, sus comentarios y
| sus evidencias. Así, guardar un cambio (estado + asignación + comentario)
| es una sola escritura atómica sobre el documento y no hacen falta las
| transacciones multi-tabla de SQL.
|
| Los catálogos (categoría, prioridad, estado) y los usuarios se guardan como
| referencias por id y sus nombres se resuelven al leer.
*/

class Incidencia
{
    const ESTADOS_FINALES = ["Resuelta", "Cerrada", "Cancelada"];
    const ESTADOS_BLOQUEADOS = ["Cerrada", "Cancelada"];
    const ESTADOS_RESPONSABLE = ["En proceso", "Resuelta"];
    const ROLES_RESPONSABLES = ["Administrador", "Coordinador", "Docente", "Administrativo"];

    /*
     * Estados fijos del flujo (equivalen a la tabla estados_incidencia).
     * No se administran desde el sistema porque el flujo depende de sus nombres.
     */
    const ESTADOS = [
        1 => "Pendiente",
        2 => "En revisión",
        3 => "Asignada",
        4 => "En proceso",
        5 => "Resuelta",
        6 => "Cerrada",
        7 => "Cancelada",
    ];

    private $db;
    private $col;

    private $catCache = null;
    private $priCache = null;
    private $rolCache = null;

    private $avisos = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->col = $db->getCollection("incidencias");
    }

    /*
    |--------------------------------------------------------------------------
    | Catálogos y usuarios (mapas en memoria)
    |--------------------------------------------------------------------------
    */

    public function obtenerEstados()
    {
        return self::ESTADOS;
    }

    private function estadoId($nombre)
    {
        $id = array_search($nombre, self::ESTADOS, true);
        return $id === false ? null : $id;
    }

    private function categorias()
    {
        if ($this->catCache === null) {
            $this->catCache = [];
            foreach ($this->db->getCollection("categorias")->find() as $c) {
                $this->catCache[(int) $c["_id"]] = $c;
            }
        }
        return $this->catCache;
    }

    private function prioridades()
    {
        if ($this->priCache === null) {
            $this->priCache = [];
            foreach ($this->db->getCollection("prioridades")->find() as $p) {
                $this->priCache[(int) $p["_id"]] = $p;
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

    private function docUsuario($id)
    {
        if ($id === null) {
            return null;
        }
        return $this->db->getCollection("usuarios")->findOne(["_id" => (int) $id]);
    }

    private static function nombreCompleto($doc)
    {
        if (!$doc) {
            return "";
        }
        return trim($doc["nombre"] . " " . ($doc["apellido_paterno"] ?? ""));
    }

    /*
     * [id => "nombre apellido"] de un conjunto de usuarios (una sola consulta).
     */
    private function mapaUsuarios(array $ids)
    {
        $ids = array_values(array_unique(array_map("intval", array_filter($ids, fn($v) => $v !== null))));

        $mapa = [];

        if (!$ids) {
            return $mapa;
        }

        foreach ($this->db->getCollection("usuarios")->find(
            ["_id" => ['$in' => $ids]],
            ["projection" => ["nombre" => 1, "apellido_paterno" => 1]]
        ) as $u) {
            $mapa[(int) $u["_id"]] = self::nombreCompleto($u);
        }

        return $mapa;
    }

    /*
    |--------------------------------------------------------------------------
    | Lectura de una incidencia con todos sus datos resueltos
    |--------------------------------------------------------------------------
    */

    public function buscarPorId($id)
    {
        $doc = $this->col->findOne(["_id" => (int) $id]);

        if (!$doc) {
            return false;
        }

        return $this->decorarDetalle($doc);
    }

    private function decorarDetalle($doc)
    {
        $categorias = $this->categorias();
        $prioridades = $this->prioridades();
        $roles = $this->roles();

        $reporta = $this->docUsuario($doc["usuario_id"]);
        $responsable = $this->docUsuario($doc["responsable_id"] ?? null);

        $cat = $categorias[(int) $doc["categoria_id"]] ?? null;
        $pri = $prioridades[(int) $doc["prioridad_id"]] ?? null;

        return [
            "id" => (int) $doc["_id"],
            "folio" => $doc["folio"],
            "usuario_id" => (int) $doc["usuario_id"],
            "categoria_id" => (int) $doc["categoria_id"],
            "prioridad_id" => (int) $doc["prioridad_id"],
            "estado_id" => $this->estadoId($doc["estado"]),
            "estado" => $doc["estado"],
            "titulo" => $doc["titulo"],
            "descripcion" => $doc["descripcion"],
            "ubicacion" => $doc["ubicacion"] ?? null,
            "carrera" => $doc["carrera"] ?? null,
            "telefono_contacto" => $doc["telefono_contacto"] ?? null,
            "responsable_id" => isset($doc["responsable_id"]) && $doc["responsable_id"] !== null ? (int) $doc["responsable_id"] : null,
            "fecha_registro" => $doc["fecha_registro"],
            "fecha_actualizacion" => $doc["fecha_actualizacion"] ?? $doc["fecha_registro"],
            "fecha_cierre" => $doc["fecha_cierre"] ?? null,

            // Datos del solicitante.
            "nombre" => $reporta["nombre"] ?? "",
            "apellido_paterno" => $reporta["apellido_paterno"] ?? "",
            "apellido_materno" => $reporta["apellido_materno"] ?? "",
            "correo" => $reporta["correo"] ?? "",
            "matricula" => $reporta["matricula"] ?? "",
            "telefono_usuario" => $reporta["telefono"] ?? "",
            "carrera_usuario" => $reporta["carrera"] ?? "",
            "rol_solicitante" => $roles[(int) ($reporta["rol_id"] ?? 0)] ?? "",

            // Catálogos y responsable.
            "categoria" => $cat["nombre"] ?? "",
            "prioridad" => $pri["nombre"] ?? "",
            "dias_atencion" => $pri["dias_atencion"] ?? 0,
            "responsable" => self::nombreCompleto($responsable),
        ];
    }

    /*
     * Fecha límite de atención: fecha de registro + días hábiles.
     */
    public static function fechaCompromiso($fechaRegistro, $dias)
    {
        $fecha = new DateTime($fechaRegistro);
        $dias = (int) $dias;

        while ($dias > 0) {
            $fecha->modify("+1 day");

            if ((int) $fecha->format("N") <= 5) {
                $dias--;
            }
        }

        return $fecha;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsables y gestores
    |--------------------------------------------------------------------------
    */

    public function obtenerResponsables()
    {
        $roles = $this->roles();

        $idsRol = array_keys(array_filter($roles, fn($n) => in_array($n, self::ROLES_RESPONSABLES, true)));

        $cursor = $this->db->getCollection("usuarios")->find(
            ["activo" => true, "rol_id" => ['$in' => array_map("intval", $idsRol)]],
            ["sort" => ["nombre" => 1, "apellido_paterno" => 1]]
        );

        $lista = [];

        foreach ($cursor as $u) {
            $lista[(int) $u["_id"]] = self::nombreCompleto($u) . " (" . ($roles[(int) $u["rol_id"]] ?? "") . ")";
        }

        return $lista;
    }

    /*
     * Ids de Administradores y Coordinadores activos.
     */
    public function obtenerGestores()
    {
        $roles = $this->roles();

        $idsRol = array_keys(array_filter($roles, fn($n) => in_array($n, ["Administrador", "Coordinador"], true)));

        $ids = [];

        foreach ($this->db->getCollection("usuarios")->find(
            ["activo" => true, "rol_id" => ['$in' => array_map("intval", $idsRol)]],
            ["projection" => ["_id" => 1]]
        ) as $u) {
            $ids[] = (int) $u["_id"];
        }

        return $ids;
    }

    private function nombreUsuario($id)
    {
        return self::nombreCompleto($this->docUsuario($id));
    }

    /*
    |--------------------------------------------------------------------------
    | Historial (eventos embebidos en el documento)
    |--------------------------------------------------------------------------
    */

    public function registrarHistorial(
        $incidencia_id,
        $usuario_id,
        $accion,
        $descripcion,
        $estado_anterior_id = null,
        $estado_nuevo_id = null
    ) {
        $this->col->updateOne(
            ["_id" => (int) $incidencia_id],
            ['$push' => ["historial" => [
                "id" => Database::siguienteId($this->db, "historial"),
                "usuario_id" => (int) $usuario_id,
                "accion" => $accion,
                "estado_anterior_id" => $estado_anterior_id,
                "estado_nuevo_id" => $estado_nuevo_id,
                "descripcion" => $descripcion,
                "fecha" => Database::ahora(),
            ]]]
        );
    }

    /*
     * Cambia el estado, maneja la fecha de cierre y deja registro en el
     * historial. $incidencia es el arreglo de buscarPorId() y se actualiza
     * para las siguientes acciones del mismo guardado.
     */
    public function cambiarEstado(&$incidencia, $estado_id, $usuario_id)
    {
        if ((int) $incidencia["estado_id"] === (int) $estado_id) {
            return false;
        }

        $nuevoEstado = self::ESTADOS[$estado_id];

        $esFinal = in_array($nuevoEstado, self::ESTADOS_FINALES, true);

        // Si el nuevo estado es final se guarda la fecha de cierre (solo la
        // primera vez); si se reabre, se limpia.
        $fechaCierre = $esFinal
            ? ($incidencia["fecha_cierre"] ?: Database::ahora())
            : null;

        $this->col->updateOne(
            ["_id" => (int) $incidencia["id"]],
            ['$set' => ["estado" => $nuevoEstado, "fecha_cierre" => $fechaCierre]]
        );

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
        $incidencia["fecha_cierre"] = $fechaCierre;

        $destinatarios = [$incidencia["usuario_id"], $incidencia["responsable_id"]];

        if (in_array($nuevoEstado, ["Resuelta", "Cancelada"], true)) {
            $destinatarios = array_merge($destinatarios, $this->obtenerGestores());
        }

        $this->avisar($destinatarios, "estado", "cambió el estado a " . $nuevoEstado, $usuario_id);

        return true;
    }

    /*
     * Catálogo activo para reclasificar, conservando el valor actual aunque
     * esté desactivado. $tabla: "categorias" o "prioridades".
     */
    public function opcionesClasificacion($tabla, $actual_id)
    {
        if (!in_array($tabla, ["categorias", "prioridades"], true)) {
            throw new InvalidArgumentException("Catálogo no válido.");
        }

        $orden = $tabla === "prioridades" ? ["nivel" => 1] : ["nombre" => 1];

        $cursor = $this->db->getCollection($tabla)->find(
            ['$or' => [["activo" => true], ["_id" => (int) $actual_id]]],
            ["sort" => $orden]
        );

        $opciones = [];

        foreach ($cursor as $x) {
            $opciones[(int) $x["_id"]] = $x["nombre"];
        }

        return $opciones;
    }

    /*
     * Corrige categoría y/o prioridad. Cada cambio queda en el historial y
     * se avisa a quien reportó y al responsable.
     */
    public function reclasificar(&$incidencia, $categoria_id, $prioridad_id, $usuario_id, array $categorias, array $prioridades)
    {
        $cambios = [];
        $frases = [];

        if ((int) $categoria_id !== (int) $incidencia["categoria_id"]) {
            $cambios[] = "Categoría: " . $incidencia["categoria"] . " → " . $categorias[$categoria_id];
            $frases[] = "cambió la categoría a " . $categorias[$categoria_id];
        }

        if ((int) $prioridad_id !== (int) $incidencia["prioridad_id"]) {
            $cambios[] = "Prioridad: " . $incidencia["prioridad"] . " → " . $prioridades[$prioridad_id];
            $frases[] = "cambió la prioridad a " . $prioridades[$prioridad_id];
        }

        if (!$cambios) {
            return false;
        }

        $this->col->updateOne(
            ["_id" => (int) $incidencia["id"]],
            ['$set' => ["categoria_id" => (int) $categoria_id, "prioridad_id" => (int) $prioridad_id]]
        );

        foreach ($cambios as $descripcion) {
            $this->registrarHistorial($incidencia["id"], $usuario_id, "clasificacion", $descripcion);
        }

        foreach ($frases as $frase) {
            $this->avisar(
                [$incidencia["usuario_id"], $incidencia["responsable_id"]],
                "clasificacion",
                $frase,
                $usuario_id
            );
        }

        $incidencia["categoria_id"] = (int) $categoria_id;
        $incidencia["categoria"] = $categorias[$categoria_id];
        $incidencia["prioridad_id"] = (int) $prioridad_id;
        $incidencia["prioridad"] = $prioridades[$prioridad_id];

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

        $this->col->updateOne(
            ["_id" => (int) $incidencia["id"]],
            ['$set' => ["responsable_id" => $nuevo]]
        );

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

    /*
     * Agrega un comentario embebido y devuelve su id (para asociarle
     * evidencias). El texto puede ir vacío si solo lleva evidencias.
     */
    public function agregarComentario($incidencia, $usuario_id, $comentario, $numEvidencias = 0)
    {
        $comentario_id = Database::siguienteId($this->db, "comentarios");

        $this->col->updateOne(
            ["_id" => (int) $incidencia["id"]],
            [
                '$push' => ["comentarios" => [
                    "id" => $comentario_id,
                    "usuario_id" => (int) $usuario_id,
                    "comentario" => $comentario,
                    "fecha" => Database::ahora(),
                ]],
                '$set' => ["fecha_actualizacion" => Database::ahora()],
            ]
        );

        $destinatarios = [$incidencia["usuario_id"], $incidencia["responsable_id"]];

        if (
            (int) $usuario_id === (int) $incidencia["usuario_id"] &&
            $incidencia["responsable_id"] === null
        ) {
            $destinatarios = array_merge($destinatarios, $this->obtenerGestores());
        }

        $frases = [];

        if ($comentario !== "") {
            $extracto = mb_strlen($comentario) > 100 ? mb_substr($comentario, 0, 100) . "…" : $comentario;
            $frases[] = "comentó: “" . $extracto . "”";
        }

        if ($numEvidencias > 0) {
            $frases[] = "adjuntó " . $numEvidencias . ($numEvidencias === 1 ? " evidencia" : " evidencias");
        }

        $this->avisar($destinatarios, "comentario", implode(" y ", $frases), $usuario_id);

        return $comentario_id;
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICACIONES
    |--------------------------------------------------------------------------
    | Las acciones solo acumulan avisos; enviarAvisos() los guarda al final,
    | uno por destinatario, aunque en un mismo guardado haya varios cambios.
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

    public function enviarAvisos($incidencia_id, $actor_id)
    {
        if (empty($this->avisos)) {
            return 0;
        }

        require_once __DIR__ . "/Notificacion.php";
        require_once __DIR__ . "/../helpers/correo_incidencias.php";

        $notificacionModel = new Notificacion($this->db);

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

            // Mismo aviso por correo (si está configurado).
            correoDeNotificacion($this->db, $usuario, $incidencia_id, $actor . " " . $texto);
        }

        $enviados = count($this->avisos);

        $this->avisos = [];

        return $enviados;
    }

    /*
    |--------------------------------------------------------------------------
    | Seguimiento (historial + comentarios juntos)
    |--------------------------------------------------------------------------
    */

    public function obtenerSeguimiento($incidencia_id)
    {
        $doc = $this->col->findOne(
            ["_id" => (int) $incidencia_id],
            ["projection" => ["historial" => 1, "comentarios" => 1]]
        );

        if (!$doc) {
            return [];
        }

        $historial = $doc["historial"] ?? [];
        $comentarios = $doc["comentarios"] ?? [];

        $ids = [];
        foreach ($historial as $h) {
            $ids[] = $h["usuario_id"];
        }
        foreach ($comentarios as $c) {
            $ids[] = $c["usuario_id"];
        }

        $usuarios = $this->mapaUsuarios($ids);
        $rolPorUsuario = $this->rolPorUsuario($ids);

        $eventos = [];

        foreach ($historial as $h) {
            $eventos[] = [
                "tipo" => "historial",
                "accion" => $h["accion"],
                "texto" => $h["descripcion"],
                "fecha" => $h["fecha"],
                "id" => (int) $h["id"],
                "usuario" => $usuarios[(int) $h["usuario_id"]] ?? "",
                "rol" => $rolPorUsuario[(int) $h["usuario_id"]] ?? "",
            ];
        }

        foreach ($comentarios as $c) {
            $eventos[] = [
                "tipo" => "comentario",
                "accion" => null,
                "texto" => $c["comentario"],
                "fecha" => $c["fecha"],
                "id" => (int) $c["id"],
                "usuario" => $usuarios[(int) $c["usuario_id"]] ?? "",
                "rol" => $rolPorUsuario[(int) $c["usuario_id"]] ?? "",
            ];
        }

        // Igual que el ORDER BY fecha, tipo DESC, id: más antiguo primero;
        // a igual fecha, el historial antes que el comentario.
        usort($eventos, function ($a, $b) {
            return [$a["fecha"], $b["tipo"], $a["id"]] <=> [$b["fecha"], $a["tipo"], $b["id"]];
        });

        return $eventos;
    }

    /*
     * [id => rol] de un conjunto de usuarios (para el seguimiento).
     */
    private function rolPorUsuario(array $ids)
    {
        $ids = array_values(array_unique(array_map("intval", $ids)));

        if (!$ids) {
            return [];
        }

        $roles = $this->roles();
        $mapa = [];

        foreach ($this->db->getCollection("usuarios")->find(
            ["_id" => ['$in' => $ids]],
            ["projection" => ["rol_id" => 1]]
        ) as $u) {
            $mapa[(int) $u["_id"]] = $roles[(int) $u["rol_id"]] ?? "";
        }

        return $mapa;
    }

    /*
    |--------------------------------------------------------------------------
    | Alta y listados (antes iban en las páginas con SQL directo)
    |--------------------------------------------------------------------------
    */

    /*
     * Crea la incidencia con su primer evento de historial embebido.
     * Devuelve el id nuevo.
     */
    public function crear(array $datos)
    {
        $id = Database::siguienteId($this->db, "incidencias");
        $ahora = Database::ahora();

        $this->col->insertOne([
            "_id" => $id,
            "folio" => $datos["folio"],
            "usuario_id" => (int) $datos["usuario_id"],
            "categoria_id" => (int) $datos["categoria_id"],
            "prioridad_id" => (int) $datos["prioridad_id"],
            "estado" => "Pendiente",
            "titulo" => $datos["titulo"],
            "descripcion" => $datos["descripcion"],
            "ubicacion" => $datos["ubicacion"] !== "" ? $datos["ubicacion"] : null,
            "carrera" => $datos["carrera"] !== "" ? $datos["carrera"] : null,
            "telefono_contacto" => $datos["telefono_contacto"] !== "" ? $datos["telefono_contacto"] : null,
            "responsable_id" => null,
            "fecha_registro" => $ahora,
            "fecha_actualizacion" => $ahora,
            "fecha_cierre" => null,
            "historial" => [[
                "id" => Database::siguienteId($this->db, "historial"),
                "usuario_id" => (int) $datos["usuario_id"],
                "accion" => "registro",
                "estado_anterior_id" => null,
                "estado_nuevo_id" => 1,
                "descripcion" => "Incidencia registrada",
                "fecha" => $ahora,
            ]],
            "comentarios" => [],
            "evidencias" => [],
        ]);

        return $id;
    }

    /*
     * Convierte documentos en filas para las tablas de listado, con los
     * nombres de catálogo, usuario y responsable ya resueltos.
     */
    private function decorarLista(array $docs)
    {
        if (!$docs) {
            return [];
        }

        $categorias = $this->categorias();
        $prioridades = $this->prioridades();

        $ids = [];
        foreach ($docs as $d) {
            $ids[] = $d["usuario_id"];
            if (($d["responsable_id"] ?? null) !== null) {
                $ids[] = $d["responsable_id"];
            }
        }
        $usuarios = $this->mapaUsuarios($ids);

        $filas = [];

        foreach ($docs as $d) {
            $filas[] = [
                "id" => (int) $d["_id"],
                "folio" => $d["folio"],
                "titulo" => $d["titulo"],
                "ubicacion" => $d["ubicacion"] ?? null,
                "fecha_registro" => $d["fecha_registro"],
                "fecha_actualizacion" => $d["fecha_actualizacion"] ?? $d["fecha_registro"],
                "usuario" => $usuarios[(int) $d["usuario_id"]] ?? "",
                "responsable" => ($d["responsable_id"] ?? null) !== null ? ($usuarios[(int) $d["responsable_id"]] ?? "") : "",
                "categoria" => $categorias[(int) $d["categoria_id"]]["nombre"] ?? "",
                "prioridad" => $prioridades[(int) $d["prioridad_id"]]["nombre"] ?? "",
                "estado" => $d["estado"],
            ];
        }

        return $filas;
    }

    /*
     * Filtro de MongoDB para "Todas las incidencias".
     * $f: estado_id, categoria_id, prioridad_id, responsable_id ('' | 'sin' | id), q
     */
    private function filtroTodas(array $f)
    {
        $filtro = [];

        if (($f["estado_id"] ?? "") !== "" && isset(self::ESTADOS[(int) $f["estado_id"]])) {
            $filtro["estado"] = self::ESTADOS[(int) $f["estado_id"]];
        }

        if (($f["categoria_id"] ?? "") !== "") {
            $filtro["categoria_id"] = (int) $f["categoria_id"];
        }

        if (($f["prioridad_id"] ?? "") !== "") {
            $filtro["prioridad_id"] = (int) $f["prioridad_id"];
        }

        if (($f["responsable_id"] ?? "") === "sin") {
            $filtro["responsable_id"] = null;
        } elseif (($f["responsable_id"] ?? "") !== "") {
            $filtro["responsable_id"] = (int) $f["responsable_id"];
        }

        if (trim($f["q"] ?? "") !== "") {
            $re = new MongoDB\BSON\Regex(preg_quote(trim($f["q"]), null), "i");
            $filtro['$or'] = [["folio" => $re], ["titulo" => $re]];
        }

        return $filtro;
    }

    public function contarTodas(array $f)
    {
        return (int) $this->col->countDocuments($this->filtroTodas($f));
    }

    public function listarTodas(array $f, $limite, $offset)
    {
        $docs = $this->col->find($this->filtroTodas($f), [
            "sort" => ["fecha_registro" => -1, "_id" => -1],
            "limit" => (int) $limite,
            "skip" => (int) $offset,
        ])->toArray();

        return $this->decorarLista($docs);
    }

    public function contarDeUsuario($usuario_id)
    {
        return (int) $this->col->countDocuments(["usuario_id" => (int) $usuario_id]);
    }

    public function listarDeUsuario($usuario_id, $limite, $offset)
    {
        $docs = $this->col->find(["usuario_id" => (int) $usuario_id], [
            "sort" => ["fecha_registro" => -1, "_id" => -1],
            "limit" => (int) $limite,
            "skip" => (int) $offset,
        ])->toArray();

        return $this->decorarLista($docs);
    }

    private function filtroAsignadas($responsable_id, $incluirTerminadas)
    {
        $filtro = ["responsable_id" => (int) $responsable_id];

        if (!$incluirTerminadas) {
            $filtro["estado"] = ['$nin' => ["Resuelta", "Cerrada", "Cancelada"]];
        }

        return $filtro;
    }

    public function contarAsignadas($responsable_id, $incluirTerminadas)
    {
        return (int) $this->col->countDocuments($this->filtroAsignadas($responsable_id, $incluirTerminadas));
    }

    /*
     * Ordenadas por prioridad (nivel) descendente y antigüedad. Usa una
     * agregación con $lookup a prioridades para ordenar por su nivel.
     */
    public function listarAsignadas($responsable_id, $incluirTerminadas, $limite, $offset)
    {
        $docs = $this->col->aggregate([
            ['$match' => $this->filtroAsignadas($responsable_id, $incluirTerminadas)],
            ['$lookup' => [
                "from" => "prioridades",
                "localField" => "prioridad_id",
                "foreignField" => "_id",
                "as" => "_pri",
            ]],
            ['$addFields' => ["_nivel" => ['$ifNull' => [['$arrayElemAt' => ['$_pri.nivel', 0]], 0]]]],
            ['$sort' => ["_nivel" => -1, "fecha_registro" => 1, "_id" => 1]],
            ['$skip' => (int) $offset],
            ['$limit' => (int) $limite],
            ['$project' => ["_pri" => 0, "_nivel" => 0]],
        ])->toArray();

        return $this->decorarLista($docs);
    }

    /*
     * Conteo por estado (para el panel/dashboard). Si se pasa $usuario_id,
     * solo cuenta las incidencias que esa persona reportó.
     */
    public function conteoPorEstado($usuario_id = null)
    {
        $pipeline = [];

        if ($usuario_id !== null) {
            $pipeline[] = ['$match' => ["usuario_id" => (int) $usuario_id]];
        }

        $pipeline[] = ['$group' => ["_id" => '$estado', "total" => ['$sum' => 1]]];

        $conteo = array_fill_keys(self::ESTADOS, 0);

        foreach ($this->col->aggregate($pipeline) as $fila) {
            $conteo[$fila["_id"]] = (int) $fila["total"];
        }

        return $conteo;
    }
}
