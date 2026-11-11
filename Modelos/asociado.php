<?php
require_once __DIR__.'/../Config/conexion.php';
class Asociado{
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

    public function __construct($datos){
        $this->documento = $datos['documento'];
        $this->nombres = $datos['nombres'];
        $this->apellidos = $datos['apellidos'];
        $this->fechaNacimiento = $datos['fechaNacimiento'];
        $this->genero = $datos['genero'];
        $this->direccionResidencia = $datos['direccionResidencia'];
        $this->ciudad = $datos['ciudad'];
        $this->email = $datos['email'];
        $this->telefono = $datos['telefono'];
        $clavePlana = $datos['contraseña'] ?? ($datos['contraseña'] ?? ($datos['clave'] ?? ($datos['password'] ?? '')));
        // Hashear la contraseña con el algoritmo seguro de PHP (bcrypt / PASSWORD_DEFAULT)
        if (strpos($clavePlana, '$2y$') === 0 || strpos($clavePlana, '$2a$') === 0) {
            $this->contraseña = $clavePlana;
        } else {
            $this->contraseña = password_hash($clavePlana, PASSWORD_DEFAULT);
        }
        $this->rol = $datos['rol'] ?? ($datos['Rol'] ?? 'Asociado');

        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
       


        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function guardar(){
    $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, email, telefono, contraseña, rol) 
            VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :email, :telefono, :contrasena, :rol)";
    
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
}