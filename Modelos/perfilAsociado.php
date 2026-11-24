<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/asociado.php';

try {
    $documentoSesion = $_SESSION['documento'] ?? '';
    if ($documentoSesion === '') {
        http_response_code(401);
        throw new Exception("Sesión no iniciada");
    }

    $modeloAsociado = new Asociado();
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $asociado = $modeloAsociado->obtenerPorDocumento($documentoSesion);
        if (!$asociado) {
            http_response_code(404);
            throw new Exception("Asociado no encontrado");
        }

        echo json_encode([
            "success" => true,
            "asociado" => $asociado
        ]);
        exit;
    }

    if ($metodo === 'PUT' || $metodo === 'POST') {
        $raw = file_get_contents("php://input");
        $datos = json_decode($raw, true);
        if (!is_array($datos)) {
            $datos = $_POST;
        }

        $datosLimpios = [
            "nombres"            => trim((string)($datos['nombres'] ?? '')),
            "apellidos"          => trim((string)($datos['apellidos'] ?? '')),
            "fechaNacimiento"    => trim((string)($datos['fechaNacimiento'] ?? '')),
            "genero"             => trim((string)($datos['genero'] ?? '')),
            "direccionResidencia"=> trim((string)($datos['direccionResidencia'] ?? '')),
            "ciudad"             => trim((string)($datos['ciudad'] ?? '')),
            "telefono"           => trim((string)($datos['telefono'] ?? '')),
            "email"              => trim((string)($datos['email'] ?? ''))
        ];

        if ($datosLimpios['nombres'] === '' || $datosLimpios['apellidos'] === '' || $datosLimpios['email'] === '') {
            http_response_code(400);
            throw new Exception("Nombres, apellidos y correo electrónico son obligatorios");
        }

        if (!filter_var($datosLimpios['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            throw new Exception("El correo electrónico no tiene un formato válido");
        }

        $actualizado = $modeloAsociado->actualizarPerfil($documentoSesion, $datosLimpios);

        if ($actualizado) {
            $_SESSION['nombre'] = trim($datosLimpios['nombres'] . ' ' . $datosLimpios['apellidos']);
            localStorageSyncHint:
            echo json_encode([
                "success" => true,
                "mensaje" => "Datos actualizados correctamente",
                "message" => "Datos actualizados correctamente",
                "nombre"  => $_SESSION['nombre']
            ]);
        } else {
            http_response_code(500);
            throw new Exception("No se pudieron actualizar los datos");
        }
        exit;
    }

    http_response_code(405);
    throw new Exception("Método no permitido");

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "mensaje" => "Error en la base de datos: " . $e->getMessage(),
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage(),
        "message" => $e->getMessage()
    ]);
}