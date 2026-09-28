<?php

/*
|--------------------------------------------------------------------------
| CATÁLOGOS ADMINISTRABLES (colecciones "categorias" y "prioridades")
|--------------------------------------------------------------------------
| Los estados y los roles NO se administran desde aquí porque el sistema
| depende de sus nombres (flujo de estados y permisos).
|
| Un elemento que ya se usa en incidencias no se elimina: se desactiva y
| deja de aparecer al registrar incidencias nuevas.
*/

class Catalogo
{
    const CATALOGOS = [
        "categorias" => [
            "coleccion" => "categorias",
            "columna" => "categoria_id",
            "largo_nombre" => 100,
            "orden" => ["nombre" => 1],
        ],
        "prioridades" => [
            "coleccion" => "prioridades",
            "columna" => "prioridad_id",
            "largo_nombre" => 50,
            "orden" => ["nivel" => 1],
        ],
    ];

    private $db;
    private $col;
    private $config;

    public function __construct($db, $catalogo)
    {
        if (!isset(self::CATALOGOS[$catalogo])) {
            throw new InvalidArgumentException("Catálogo no válido.");
        }

        $this->db = $db;
        $this->config = self::CATALOGOS[$catalogo];
        $this->col = $db->getCollection($this->config["coleccion"]);
    }

    /*
     * Cuántas incidencias usan cada elemento del catálogo (un solo grupo).
     */
    private function conteoUso()
    {
        $columna = $this->config["columna"];

        $conteo = [];

        foreach ($this->db->getCollection("incidencias")->aggregate([
            ['$group' => ["_id" => '$' . $columna, "total" => ['$sum' => 1]]],
        ]) as $fila) {
            if ($fila["_id"] !== null) {
                $conteo[(int) $fila["_id"]] = (int) $fila["total"];
            }
        }

        return $conteo;
    }

    public function listar()
    {
        $orden = ["activo" => -1] + $this->config["orden"];

        $uso = $this->conteoUso();

        $lista = [];

        foreach ($this->col->find([], ["sort" => $orden]) as $doc) {
            $doc["id"] = (int) $doc["_id"];
            $doc["incidencias"] = $uso[(int) $doc["_id"]] ?? 0;
            $lista[] = $doc;
        }

        return $lista;
    }

    public function buscarPorId($id)
    {
        $doc = $this->col->findOne(["_id" => (int) $id]);

        if ($doc) {
            $doc["id"] = (int) $doc["_id"];
        }

        return $doc;
    }

    /*
     * Valida y guarda (alta si $id es null, edición si no).
     * Devuelve un mensaje de error o null si se guardó.
     */
    public function guardar($id, array $datos)
    {
        $coleccion = $this->config["coleccion"];

        $nombre = trim($datos["nombre"] ?? "");

        if ($nombre === "") {
            return "El nombre es obligatorio.";
        }

        if (mb_strlen($nombre) > $this->config["largo_nombre"]) {
            return "El nombre admite máximo " . $this->config["largo_nombre"] . " caracteres.";
        }

        if ($this->existe("nombre", $nombre, $id)) {
            return "Ya existe un elemento con ese nombre.";
        }

        if ($coleccion === "categorias") {

            $descripcion = trim($datos["descripcion"] ?? "");

            if (mb_strlen($descripcion) > 255) {
                return "La descripción admite máximo 255 caracteres.";
            }

            $campos = ["nombre" => $nombre, "descripcion" => $descripcion !== "" ? $descripcion : null];

        } else {

            $nivel = $datos["nivel"] ?? "";

            if (!ctype_digit((string) $nivel) || (int) $nivel < 1 || (int) $nivel > 99) {
                return "El nivel debe ser un número entre 1 y 99 (mayor = más urgente).";
            }

            if ($this->existe("nivel", (int) $nivel, $id)) {
                return "Ya existe una prioridad con ese nivel.";
            }

            $dias = $datos["dias_atencion"] ?? "";

            if (!ctype_digit((string) $dias) || (int) $dias < 1 || (int) $dias > 60) {
                return "El tiempo de atención debe ser de 1 a 60 días hábiles.";
            }

            $campos = ["nombre" => $nombre, "nivel" => (int) $nivel, "dias_atencion" => (int) $dias];
        }

        if ($id === null) {
            $campos["_id"] = Database::siguienteId($this->db, $coleccion);
            $campos["activo"] = true;
            $this->col->insertOne($campos);
        } else {
            $this->col->updateOne(["_id" => (int) $id], ['$set' => $campos]);
        }

        return null;
    }

    /*
     * Activa o desactiva. Siempre debe quedar al menos un elemento activo.
     */
    public function cambiarActivo($id, $activo)
    {
        if (!$activo) {

            $activos = (int) $this->col->countDocuments(["activo" => true]);

            $elemento = $this->buscarPorId($id);

            if ($elemento && !empty($elemento["activo"]) && $activos <= 1) {
                return "Debe quedar al menos un elemento activo.";
            }
        }

        $this->col->updateOne(["_id" => (int) $id], ['$set' => ["activo" => (bool) $activo]]);

        return null;
    }

    /*
     * Solo se elimina si ninguna incidencia lo usa.
     */
    public function eliminar($id)
    {
        $columna = $this->config["columna"];

        $enUso = $this->db->getCollection("incidencias")->countDocuments([$columna => (int) $id]);

        if ($enUso > 0) {
            return "No se puede eliminar porque hay incidencias que lo usan. Desactívalo en su lugar.";
        }

        $elemento = $this->buscarPorId($id);

        if ($elemento && !empty($elemento["activo"])) {

            $activos = (int) $this->col->countDocuments(["activo" => true]);

            if ($activos <= 1) {
                return "Debe quedar al menos un elemento activo.";
            }
        }

        $this->col->deleteOne(["_id" => (int) $id]);

        return null;
    }

    private function existe($campo, $valor, $excluir_id)
    {
        return $this->col->countDocuments([
            $campo => $valor,
            "_id" => ['$ne' => (int) ($excluir_id ?? 0)],
        ]) > 0;
    }
}
