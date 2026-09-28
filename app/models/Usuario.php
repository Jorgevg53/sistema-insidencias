<?php

/*
|--------------------------------------------------------------------------
| USUARIOS (colección "usuarios" de MongoDB)
|--------------------------------------------------------------------------
| Cada usuario es un documento. El rol se guarda como referencia (rol_id)
| a la colección "roles"; el nombre del rol se resuelve al leer.
*/

class Usuario
{
    private $db;
    private $col;
    private $rolesCache = null;

    public function __construct($db)
    {
        $this->db = $db;
        $this->col = $db->getCollection("usuarios");
    }

    /*
     * Mapa [rol_id => ["nombre" => ..., "activo" => ...]] (en memoria).
     */
    private function roles()
    {
        if ($this->rolesCache === null) {

            $this->rolesCache = [];

            foreach ($this->db->getCollection("roles")->find([], ["sort" => ["_id" => 1]]) as $rol) {
                $this->rolesCache[(int) $rol["_id"]] = [
                    "nombre" => $rol["nombre"],
                    "activo" => !empty($rol["activo"]),
                ];
            }
        }

        return $this->rolesCache;
    }

    private function nombreRol($rol_id)
    {
        $roles = $this->roles();
        return $roles[(int) $rol_id]["nombre"] ?? "";
    }

    /*
     * Convierte un documento de MongoDB al arreglo que espera el sistema
     * (con "id" en vez de "_id" y el nombre del rol resuelto).
     */
    private function normalizar($doc)
    {
        if (!$doc) {
            return $doc;
        }

        $doc["id"] = (int) $doc["_id"];
        $doc["rol"] = $this->nombreRol($doc["rol_id"] ?? 0);

        return $doc;
    }

    public function buscarPorCorreo($correo)
    {
        $doc = $this->col->findOne(["correo" => $correo, "activo" => true]);

        return $doc ? $this->normalizar($doc) : false;
    }

    public function buscarPorId($id)
    {
        $doc = $this->col->findOne(["_id" => (int) $id]);

        return $doc ? $this->normalizar($doc) : false;
    }

    /*
     * Filtro de MongoDB compartido por listar() y contar().
     */
    private function filtro($texto, $rol_id, $activo)
    {
        $filtro = [];

        if ($texto !== "") {
            $re = new MongoDB\BSON\Regex(preg_quote($texto, null), "i");
            $filtro['$or'] = [
                ["nombre" => $re],
                ["apellido_paterno" => $re],
                ["apellido_materno" => $re],
                ["correo" => $re],
                ["matricula" => $re],
            ];
        }

        if ($rol_id !== "" && $rol_id !== null) {
            $filtro["rol_id"] = (int) $rol_id;
        }

        if ($activo !== "" && $activo !== null) {
            $filtro["activo"] = (bool) (int) $activo;
        }

        return $filtro;
    }

    public function contar($texto = "", $rol_id = "", $activo = "")
    {
        return (int) $this->col->countDocuments($this->filtro($texto, $rol_id, $activo));
    }

    public function listar($texto = "", $rol_id = "", $activo = "", $limite = null, $offset = 0)
    {
        $opciones = [
            "sort" => ["nombre" => 1, "apellido_paterno" => 1, "_id" => 1],
        ];

        if ($limite !== null) {
            $opciones["limit"] = (int) $limite;
            $opciones["skip"] = (int) $offset;
        }

        $lista = [];

        foreach ($this->col->find($this->filtro($texto, $rol_id, $activo), $opciones) as $doc) {
            $lista[] = $this->normalizar($doc);
        }

        return $lista;
    }

    /*
     * [rol_id => nombre] de los roles activos.
     */
    public function obtenerRoles()
    {
        $roles = [];

        foreach ($this->roles() as $id => $rol) {
            if ($rol["activo"]) {
                $roles[$id] = $rol["nombre"];
            }
        }

        return $roles;
    }

    /*
     * Indica si un valor (correo o matrícula) ya lo usa otro usuario
     * distinto de $excluir_id.
     */
    public function existe($campo, $valor, $excluir_id = 0)
    {
        if (!in_array($campo, ["correo", "matricula"], true)) {
            return false;
        }

        return $this->col->countDocuments([
            $campo => $valor,
            "_id" => ['$ne' => (int) $excluir_id],
        ]) > 0;
    }

    public function crear($datos)
    {
        $id = Database::siguienteId($this->db, "usuarios");

        $this->col->insertOne([
            "_id" => $id,
            "matricula" => $datos["matricula"],
            "nombre" => $datos["nombre"],
            "apellido_paterno" => $datos["apellido_paterno"],
            "apellido_materno" => $datos["apellido_materno"],
            "correo" => $datos["correo"],
            "password" => password_hash($datos["password"], PASSWORD_DEFAULT),
            "rol_id" => (int) $datos["rol_id"],
            "departamento" => $datos["departamento"],
            "carrera" => $datos["carrera"],
            "telefono" => $datos["telefono"],
            "activo" => true,
            "fecha_registro" => Database::ahora(),
        ]);

        return $id;
    }

    public function actualizar($id, $datos)
    {
        $this->col->updateOne(
            ["_id" => (int) $id],
            ['$set' => [
                "matricula" => $datos["matricula"],
                "nombre" => $datos["nombre"],
                "apellido_paterno" => $datos["apellido_paterno"],
                "apellido_materno" => $datos["apellido_materno"],
                "correo" => $datos["correo"],
                "rol_id" => (int) $datos["rol_id"],
                "departamento" => $datos["departamento"],
                "carrera" => $datos["carrera"],
                "telefono" => $datos["telefono"],
            ]]
        );

        if (!empty($datos["password"])) {
            $this->cambiarPassword($id, $datos["password"]);
        }
    }

    public function cambiarPassword($id, $password)
    {
        $this->col->updateOne(
            ["_id" => (int) $id],
            ['$set' => ["password" => password_hash($password, PASSWORD_DEFAULT)]]
        );
    }

    public function cambiarActivo($id, $activo)
    {
        $this->col->updateOne(
            ["_id" => (int) $id],
            ['$set' => ["activo" => (bool) $activo]]
        );
    }

    /*
     * Datos de contacto que cada usuario puede editar en "Mi perfil".
     */
    public function actualizarContacto($id, $carrera, $telefono)
    {
        $this->col->updateOne(
            ["_id" => (int) $id],
            ['$set' => ["carrera" => $carrera, "telefono" => $telefono]]
        );
    }
}
