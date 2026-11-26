<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    $documentoSesion = trim((string)($_SESSION['documento'] ?? ''));
    $rolSesion = strtolower(trim((string)($_SESSION['rol'] ?? $_SESSION['Rol'] ?? '')));

    if ($documentoSesion === '' || $rolSesion === '') {
        http_response_code(401);
        throw new Exception('Inicia sesión para consultar los asociados');
    }

    if (!in_array($rolSesion, ['admin', 'administrador'], true)) {
        http_response_code(403);
        throw new Exception('Solo un administrador puede gestionar asociados');
    }

    $metodo = $_SERVER['REQUEST_METHOD'];
    if (!in_array($metodo, ['GET', 'PUT', 'DELETE'], true)) {
        http_response_code(405);
        throw new Exception('Método HTTP no permitido');
    }

    $conexion = (new Conexion())->conectar();

    if ($metodo === 'GET') {
        $consulta = $conexion->prepare("SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento, genero,
                                              direccionResidencia, ciudad, telefono, email, rol
                                       FROM asociados
                                       ORDER BY nombres ASC, apellidos ASC");
        $consulta->execute();
        echo json_encode(['success' => true, 'asociados' => $consulta->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception('Datos inválidos');
    }

    $id = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        http_response_code(400);
        throw new Exception('Identificador de asociado no válido');
    }

    if ($metodo === 'DELETE') {
        $consulta = $conexion->prepare('DELETE FROM asociados WHERE idAsociado = :id');
        $consulta->execute([':id' => $id]);

        if ($consulta->rowCount() === 0) {
            http_response_code(404);
            throw new Exception('No se encontró el asociado');
        }

        echo json_encode(['success' => true, 'message' => 'Asociado eliminado correctamente']);
        exit;
    }

    $campos = ['documento', 'nombres', 'apellidos', 'genero', 'direccionResidencia', 'ciudad', 'telefono', 'email'];
    foreach ($campos as $campo) {
        if (trim((string)($datos[$campo] ?? '')) === '') {
            http_response_code(400);
            throw new Exception('Todos los campos obligatorios deben estar completos');
        }
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception('El correo electrónico no es válido');
    }

    $fechaNacimiento = null;
    if (!empty($datos['fechaNacimiento'])) {
        $fechaObj = DateTime::createFromFormat('Y-m-d', (string)$datos['fechaNacimiento']);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== (string)$datos['fechaNacimiento']) {
            http_response_code(400);
            throw new Exception('La fecha de nacimiento no tiene un formato válido (YYYY-MM-DD)');
        }
        $fechaNacimiento = (string)$datos['fechaNacimiento'];
    }

    $consulta = $conexion->prepare("UPDATE asociados
                                    SET documento = :documento,
                                        nombres = :nombres,
                                        apellidos = :apellidos,
                                        fechaNacimiento = :fechaNacimiento,
                                        genero = :genero,
                                        direccionResidencia = :direccionResidencia,
                                        ciudad = :ciudad,
                                        telefono = :telefono,
                                        email = :email
                                    WHERE idAsociado = :id");
    $consulta->execute([
        ':documento' => trim((string)$datos['documento']),
        ':nombres' => trim((string)$datos['nombres']),
        ':apellidos' => trim((string)$datos['apellidos']),
        ':fechaNacimiento' => $fechaNacimiento,
        ':genero' => trim((string)$datos['genero']),
        ':direccionResidencia' => trim((string)$datos['direccionResidencia']),
        ':ciudad' => trim((string)$datos['ciudad']),
        ':telefono' => trim((string)$datos['telefono']),
        ':email' => trim((string)$datos['email']),
        ':id' => $id
    ]);

    if ($consulta->rowCount() === 0) {
        $existe = $conexion->prepare('SELECT 1 FROM asociados WHERE idAsociado = :id');
        $existe->execute([':id' => $id]);
        if (!$existe->fetchColumn()) {
            http_response_code(404);
            throw new Exception('No se encontró el asociado');
        }
    }

    echo json_encode(['success' => true, 'message' => 'Asociado actualizado correctamente']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ya existe otro asociado con ese documento o correo electrónico']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}