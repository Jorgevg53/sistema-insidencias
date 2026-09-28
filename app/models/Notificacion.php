<?php

/*
|--------------------------------------------------------------------------
| NOTIFICACIONES (colección "notificaciones" de MongoDB)
|--------------------------------------------------------------------------
| Bandeja de avisos por usuario. Guarda referencias a la incidencia y al
| autor de la acción; el folio, el título y el nombre del autor se resuelven
| al listar.
*/

class Notificacion
{
    private $db;
    private $col;

    public function __construct($db)
    {
        $this->db = $db;
        $this->col = $db->getCollection("notificaciones");
    }

    public function crear($usuario_id, $actor_id, $incidencia_id, $tipo, $mensaje)
    {
        $this->col->insertOne([
            "_id" => Database::siguienteId($this->db, "notificaciones"),
            "usuario_id" => (int) $usuario_id,
            "actor_id" => $actor_id !== null ? (int) $actor_id : null,
            "incidencia_id" => $incidencia_id !== null ? (int) $incidencia_id : null,
            "tipo" => $tipo,
            "mensaje" => mb_substr($mensaje, 0, 500),
            "leida" => false,
            "fecha" => Database::ahora(),
            "fecha_lectura" => null,
        ]);
    }

    public function contarNoLeidas($usuario_id)
    {
        return (int) $this->col->countDocuments([
            "usuario_id" => (int) $usuario_id,
            "leida" => false,
        ]);
    }

    public function contar($usuario_id, $soloNoLeidas = false)
    {
        $filtro = ["usuario_id" => (int) $usuario_id];

        if ($soloNoLeidas) {
            $filtro["leida"] = false;
        }

        return (int) $this->col->countDocuments($filtro);
    }

    public function listar($usuario_id, $soloNoLeidas = false, $limite = 100, $offset = 0)
    {
        $filtro = ["usuario_id" => (int) $usuario_id];

        if ($soloNoLeidas) {
            $filtro["leida"] = false;
        }

        $docs = $this->col->find($filtro, [
            "sort" => ["fecha" => -1, "_id" => -1],
            "limit" => (int) $limite,
            "skip" => (int) $offset,
        ])->toArray();

        if (!$docs) {
            return [];
        }

        // Resolución en bloque de folios/títulos y nombres de los autores.
        $incidencias = [];
        $actores = [];

        foreach ($docs as $doc) {
            if ($doc["incidencia_id"] !== null) {
                $incidencias[(int) $doc["incidencia_id"]] = true;
            }
            if ($doc["actor_id"] !== null) {
                $actores[(int) $doc["actor_id"]] = true;
            }
        }

        $mapaIncidencias = [];

        if ($incidencias) {
            foreach ($this->db->getCollection("incidencias")->find(
                ["_id" => ['$in' => array_map("intval", array_keys($incidencias))]],
                ["projection" => ["folio" => 1, "titulo" => 1]]
            ) as $inc) {
                $mapaIncidencias[(int) $inc["_id"]] = $inc;
            }
        }

        $mapaActores = [];

        if ($actores) {
            foreach ($this->db->getCollection("usuarios")->find(
                ["_id" => ['$in' => array_map("intval", array_keys($actores))]],
                ["projection" => ["nombre" => 1, "apellido_paterno" => 1]]
            ) as $u) {
                $mapaActores[(int) $u["_id"]] = trim($u["nombre"] . " " . ($u["apellido_paterno"] ?? ""));
            }
        }

        $lista = [];

        foreach ($docs as $doc) {

            $inc = $doc["incidencia_id"] !== null ? ($mapaIncidencias[(int) $doc["incidencia_id"]] ?? null) : null;

            $lista[] = [
                "id" => (int) $doc["_id"],
                "incidencia_id" => $doc["incidencia_id"],
                "actor_id" => $doc["actor_id"],
                "tipo" => $doc["tipo"],
                "mensaje" => $doc["mensaje"],
                "leida" => !empty($doc["leida"]) ? 1 : 0,
                "fecha" => $doc["fecha"],
                "folio" => $inc["folio"] ?? null,
                "titulo" => $inc["titulo"] ?? null,
                "actor" => $doc["actor_id"] !== null ? ($mapaActores[(int) $doc["actor_id"]] ?? "") : "",
            ];
        }

        return $lista;
    }

    public function marcarLeida($id, $usuario_id)
    {
        $this->col->updateOne(
            ["_id" => (int) $id, "usuario_id" => (int) $usuario_id, "leida" => false],
            ['$set' => ["leida" => true, "fecha_lectura" => Database::ahora()]]
        );
    }

    /*
     * Al abrir una incidencia se dan por leídos sus avisos.
     */
    public function marcarLeidasDeIncidencia($incidencia_id, $usuario_id)
    {
        $this->col->updateMany(
            ["incidencia_id" => (int) $incidencia_id, "usuario_id" => (int) $usuario_id, "leida" => false],
            ['$set' => ["leida" => true, "fecha_lectura" => Database::ahora()]]
        );
    }

    public function marcarTodasLeidas($usuario_id)
    {
        $resultado = $this->col->updateMany(
            ["usuario_id" => (int) $usuario_id, "leida" => false],
            ['$set' => ["leida" => true, "fecha_lectura" => Database::ahora()]]
        );

        return $resultado->getModifiedCount();
    }

    public function eliminarLeidas($usuario_id)
    {
        $resultado = $this->col->deleteMany([
            "usuario_id" => (int) $usuario_id,
            "leida" => true,
        ]);

        return $resultado->getDeletedCount();
    }
}
