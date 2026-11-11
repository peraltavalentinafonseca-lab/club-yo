<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

try {
    if($_SERVER['REQUEST_METHOD']!=='POST'){
        http_response_code(405);

        //Saltar a cathch y mostrar el mensaje de error
        throw new Exception("Método HTTP no permitido");
    }

    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Datos inválidos");
    }

    $email = $datos['email'] ?? "";
    $contraseña = $datos['contraseña'] ?? "";

    if (!is_string($email) || !is_string($contraseña) || $email === "" || $contraseña === "") {
        http_response_code(400);
        throw new Exception ("Email o contraseña inválidos");
    }

    require_once __DIR__ . '/../Confi/conexion.php';
    $conexionObj = new Conexion();
    $conexion = $conexionObj->conectar();

    $consulta = $conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado =$consulta->fetch();

    if (!$asociado || !password_verify($contraseña, $asociado['contraseña']) || empty($asociado['contraseña'])) {
        http_response_code(401);
        throw new Exception("Email o contraseña incorrectos");
    }

    $_SESSION["rol"]= strtolower($asociado['rol']);
    $_SESSION["documento"]= $asociado['documento'];

    echo json_encode([
        "success" => true,
        "rol" => $_SESSION["rol"],
        "nombre" => $asociado["nombres"] . " ". $asociado["apellidos"],
    ]);
} catch (PDOException $e) {
   $errorMessage = "Error: " . $e->getMessage();
    echo json_encode([
        "success" => false,
        "mensaje" => $errorMessage
    ]);
}catch(Exception $e){
    echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
}