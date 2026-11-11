<?php

require_once __DIR__ . '/../Config/conexion.php';

class Asociado
{
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

    private $conexion;

    public function __construct(array $datos)
    {
        $this->documento = $datos['documento'] ?? null;
        $this->nombres = $datos['nombres'] ?? null;
        $this->apellidos = $datos['apellidos'] ?? null;
        $this->fechaNacimiento = $datos['fechaNacimiento'] ?? null;
        $this->genero = $datos['genero'] ?? null;
        $this->direccionResidencia = $datos['direccionResidencia'] ?? null;
        $this->ciudad = $datos['ciudad'] ?? null;
        $this->email = $datos['email'] ?? null;
        $this->telefono = $datos['telefono'] ?? null;
        $this->contraseña= password_hash($datos['contraseña'], PASSWORD_DEFAULT);

        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }
    public function guardar(): bool
    {
        $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, email, telefono, contraseña)
                VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :email, :telefono, :contraseña)";
        $consulta = $this->conexion->prepare($sql);

        return $consulta->execute([
            ':documento' => $this->documento,
            ':nombres' => $this->nombres,
            ':apellidos' => $this->apellidos,
            ':fechaNacimiento' => $this->fechaNacimiento,
            ':genero' => $this->genero,
            ':direccionResidencia' => $this->direccionResidencia,
            ':ciudad' => $this->ciudad,
            ':email' => $this->email,
            ':telefono' => $this->telefono,
            ':contraseña' => $this->contraseña
        ]);
    }
}