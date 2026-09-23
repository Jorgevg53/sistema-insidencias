<?php

/*
|--------------------------------------------------------------------------
| CATÁLOGOS ADMINISTRABLES
|--------------------------------------------------------------------------
| Categorías y prioridades. Los estados y los roles NO se administran
| desde aquí porque el sistema depende de sus nombres (flujo de estados
| y permisos).
|
| Un elemento que ya se usa en incidencias no se elimina: se desactiva
| y deja de aparecer al registrar incidencias nuevas.
*/

class Catalogo
{
    /*
     * Configuración de cada catálogo: tabla, columna en incidencias,
     * largo máximo del nombre y orden del listado.
     */
    const CATALOGOS = [
        "categorias" => [
            "tabla" => "categorias",
            "columna" => "categoria_id",
            "largo_nombre" => 100,
            "orden" => "c.nombre"
        ],
        "prioridades" => [
            "tabla" => "prioridades",
            "columna" => "prioridad_id",
            "largo_nombre" => 50,
            "orden" => "c.nivel"
        ]
    ];

    private $conn;
    private $config;

    public function __construct($db, $catalogo)
    {
        if (!isset(self::CATALOGOS[$catalogo])) {
            throw new InvalidArgumentException("Catálogo no válido.");
        }

        $this->conn = $db;
        $this->config = self::CATALOGOS[$catalogo];
    }

    /*
     * Elementos del catálogo con cuántas incidencias los usan.
     */
    public function listar()
    {
        $tabla = $this->config["tabla"];
        $columna = $this->config["columna"];

        return $this->conn->query("
            SELECT
                c.*,
                (SELECT COUNT(*) FROM incidencias i WHERE i.$columna = c.id) AS incidencias
            FROM $tabla c
            ORDER BY c.activo DESC, {$this->config["orden"]}
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->config["tabla"]} WHERE id = ?");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * Valida y guarda (alta si $id es null, edición si no).
     * Devuelve un mensaje de error o null si se guardó.
     */
    public function guardar($id, array $datos)
    {
        $tabla = $this->config["tabla"];

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

        if ($tabla === "categorias") {

            $descripcion = trim($datos["descripcion"] ?? "");

            if (mb_strlen($descripcion) > 255) {
                return "La descripción admite máximo 255 caracteres.";
            }

            $valores = [$nombre, $descripcion !== "" ? $descripcion : null];

            $sqlAlta = "INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)";
            $sqlEdicion = "UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?";

        } else {

            $nivel = $datos["nivel"] ?? "";

            if (!ctype_digit((string) $nivel) || (int) $nivel < 1 || (int) $nivel > 99) {
                return "El nivel debe ser un número entre 1 y 99 (mayor = más urgente).";
            }

            if ($this->existe("nivel", (int) $nivel, $id)) {
                return "Ya existe una prioridad con ese nivel.";
            }

            $valores = [$nombre, (int) $nivel];

            $sqlAlta = "INSERT INTO prioridades (nombre, nivel) VALUES (?, ?)";
            $sqlEdicion = "UPDATE prioridades SET nombre = ?, nivel = ? WHERE id = ?";
        }

        if ($id === null) {
            $this->conn->prepare($sqlAlta)->execute($valores);
        } else {
            $valores[] = $id;
            $this->conn->prepare($sqlEdicion)->execute($valores);
        }

        return null;
    }

    /*
     * Activa o desactiva. Siempre debe quedar al menos un elemento
     * activo para poder registrar incidencias.
     */
    public function cambiarActivo($id, $activo)
    {
        $tabla = $this->config["tabla"];

        if (!$activo) {

            $activos = (int) $this->conn->query("SELECT COUNT(*) FROM $tabla WHERE activo = 1")->fetchColumn();

            $elemento = $this->buscarPorId($id);

            if ($elemento["activo"] && $activos <= 1) {
                return "Debe quedar al menos un elemento activo.";
            }
        }

        $this->conn
            ->prepare("UPDATE $tabla SET activo = ? WHERE id = ?")
            ->execute([$activo ? 1 : 0, $id]);

        return null;
    }

    /*
     * Solo se elimina si ninguna incidencia lo usa.
     */
    public function eliminar($id)
    {
        $tabla = $this->config["tabla"];
        $columna = $this->config["columna"];

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM incidencias WHERE $columna = ?");

        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            return "No se puede eliminar porque hay incidencias que lo usan. Desactívalo en su lugar.";
        }

        $elemento = $this->buscarPorId($id);

        if ($elemento["activo"]) {

            $activos = (int) $this->conn->query("SELECT COUNT(*) FROM $tabla WHERE activo = 1")->fetchColumn();

            if ($activos <= 1) {
                return "Debe quedar al menos un elemento activo.";
            }
        }

        $this->conn->prepare("DELETE FROM $tabla WHERE id = ?")->execute([$id]);

        return null;
    }

    private function existe($campo, $valor, $excluir_id)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$this->config["tabla"]}
            WHERE $campo = ?
            AND id <> ?
        ");

        $stmt->execute([$valor, $excluir_id ?? 0]);

        return $stmt->fetchColumn() > 0;
    }
}
