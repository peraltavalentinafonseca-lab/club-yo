<?php
require_once __DIR__ . '/../Config/conexion.php';

class Asociado {
    private $documento;
    private $nombres;
    private $apellidos;
    private $fechaNacimiento;
    private $genero;
    private $direccionResidencia;
    private $ciudad;
    private $telefono;
    private $email;
    private $contraseña;
    private $rol;

    private $conexion;

    public function __construct($datos = []) {
        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();

        if (!is_array($datos) || empty($datos)) {
            return;
        }

        $this->documento = $datos['documento'] ?? null;
        $this->nombres = $datos['nombres'] ?? null;
        $this->apellidos = $datos['apellidos'] ?? null;
        $this->fechaNacimiento = $datos['fechaNacimiento'] ?? null;
        $this->genero = $datos['genero'] ?? null;
        $this->direccionResidencia = $datos['direccionResidencia'] ?? null;
        $this->ciudad = $datos['ciudad'] ?? null;
        $this->telefono = $datos['telefono'] ?? null;
        $this->email = $datos['email'] ?? null;

        $clavePlana = $datos['contraseña'] ?? ($datos['contrasena'] ?? ($datos['clave'] ?? ($datos['password'] ?? '')));
        if ($clavePlana !== '') {
            if (strpos($clavePlana, '$2y$') === 0 || strpos($clavePlana, '$2a$') === 0) {
                $this->contraseña = $clavePlana;
            } else {
                $this->contraseña = password_hash($clavePlana, PASSWORD_DEFAULT);
            }
        } else {
            $this->contraseña = null;
        }

        $this->rol = $datos['rol'] ?? ($datos['Rol'] ?? 'asociado');
    }

    public function guardar() {
        $sql = "INSERT INTO asociados (
                    documento, nombres, apellidos, fechaNacimiento, genero,
                    direccionResidencia, ciudad, email, telefono, contraseña, rol
                ) VALUES (
                    :documento, :nombres, :apellidos, :fechaNacimiento, :genero,
                    :direccionResidencia, :ciudad, :email, :telefono, :contrasena, :rol
                )";

        $consulta = $this->conexion->prepare($sql);

        return $consulta->execute([
            ":documento" => $this->documento,
            ":nombres" => $this->nombres,
            ":apellidos" => $this->apellidos,
            ":fechaNacimiento" => $this->fechaNacimiento,
            ":genero" => $this->genero,
            ":direccionResidencia" => $this->direccionResidencia,
            ":ciudad" => $this->ciudad,
            ":email" => $this->email,
            ":telefono" => $this->telefono,
            ":contrasena" => $this->contraseña,
            ":rol" => $this->rol
        ]);
    }

    public function obtenerPorDocumento($documento) {
        $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento, genero,
                       direccionResidencia, ciudad, telefono, email, rol
                FROM asociados
                WHERE documento = :documento
                LIMIT 1";

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":documento" => $documento]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarPerfil($documento, $datos) {
        $sql = "UPDATE asociados
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    fechaNacimiento = :fechaNacimiento,
                    genero = :genero,
                    direccionResidencia = :direccionResidencia,
                    ciudad = :ciudad,
                    telefono = :telefono,
                    email = :email
                WHERE documento = :documento";

        $consulta = $this->conexion->prepare($sql);

        return $consulta->execute([
            ":nombres" => $datos['nombres'],
            ":apellidos" => $datos['apellidos'],
            ":fechaNacimiento" => !empty($datos['fechaNacimiento']) ? $datos['fechaNacimiento'] : null,
            ":genero" => $datos['genero'],
            ":direccionResidencia" => $datos['direccionResidencia'],
            ":ciudad" => $datos['ciudad'],
            ":telefono" => $datos['telefono'],
            ":email" => $datos['email'],
            ":documento" => $documento
        ]);
    }
}