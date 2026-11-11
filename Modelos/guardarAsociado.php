<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/Asociado.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido. Solo se permite POST');
    }

    // Leer el contenido JSON enviado por fetch o FormData
    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        if (!empty($_POST)) {
            $datos = $_POST;
        } else {
            http_response_code(400);
            throw new Exception('Datos inválidos en la solicitud');
        }
    }

    // Normalizar contraseña sin ñ (acepta 'contrasena', 'contraseña', 'clave', 'password')
    $pass = $datos['contrasena'] ?? ($datos['contraseña'] ?? ($datos['clave'] ?? ($datos['password'] ?? '')));
    $confirmPass = $datos['confirmarContrasena'] ?? ($datos['confirmarContraseña'] ?? ($datos['confirmPassword'] ?? null));

    if (trim((string)$pass) === '') {
        http_response_code(400);
        throw new Exception("Falta el campo: contrasena");
    }

    // Validar que la contraseña tenga mínimo 8 caracteres
    if (strlen($pass) < 8) {
        http_response_code(400);
        throw new Exception("La contraseña debe tener mínimo 8 caracteres");
    }

    // Comparar contraseñas si se envió campo de confirmación
    if ($confirmPass !== null && trim((string)$confirmPass) !== '' && $pass !== $confirmPass) {
        http_response_code(400);
        throw new Exception("Las contraseñas no coinciden");
    }

    $datos['contrasena'] = $pass;
    $datos['contraseña'] = $pass;

    // Normalizar dirección de residencia
    if (!isset($datos['direccionResidencia']) || trim((string)$datos['direccionResidencia']) === '') {
        if (isset($datos['direcciónResidencia'])) {
            $datos['direccionResidencia'] = $datos['direcciónResidencia'];
        } elseif (isset($datos['direccion'])) {
            $datos['direccionResidencia'] = $datos['direccion'];
        } elseif (isset($datos['Direccion'])) {
            $datos['direccionResidencia'] = $datos['Direccion'];
        }
    }

    // Remover campos de confirmación que no van a la base de datos
    unset($datos['confirmarContrasena'], $datos['confirmarContraseña'], $datos['confirmPassword']);

    $campos = ["documento", "nombres", "apellidos", "direccionResidencia", "ciudad", "telefono", "email", "genero", "fechaNacimiento", "contrasena"];
    foreach ($campos as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            http_response_code(400);
            throw new Exception("Falta el campo: " . $campo);
        }
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("Email inválido");
    }

    $asociado = new Asociado($datos);
    $resultado = $asociado->guardar();

    if ($resultado) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Asociado guardado exitosamente"]);
    } else {
        http_response_code(500);
        throw new Exception("Error al guardar el asociado");
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El documento ya existe en la base de datos"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}