<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    $documento = trim((string)($_SESSION['documento'] ?? ''));
    $rol = strtolower(trim((string)($_SESSION['rol'] ?? $_SESSION['Rol'] ?? '')));

    if ($documento === '' || $rol === '') {
        http_response_code(401);
        throw new Exception('Inicia sesión para gestionar préstamos');
    }

    if (!in_array($rol, ['admin', 'administrador'], true)) {
        http_response_code(403);
        throw new Exception('Solo un administrador puede gestionar préstamos');
    }

    $conexion = (new Conexion())->conectar();
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $sqlAsociados = "SELECT idAsociado, documento, nombres, apellidos
                         FROM asociados
                         ORDER BY nombres ASC, apellidos ASC";
        $asociados = $conexion->query($sqlAsociados)->fetchAll(PDO::FETCH_ASSOC);

        $sqlPrestamos = "SELECT p.idPrestamo, p.idAsociado, p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo,
                                a.nombres, a.apellidos, a.documento,
                                CONCAT(a.nombres, ' ', a.apellidos) AS nombreAsociado
                         FROM prestamos p
                         INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                         ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";
        $prestamos = $conexion->query($sqlPrestamos)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'asociados' => $asociados,
            'prestamos' => $prestamos
        ]);
        exit;
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception('Datos JSON inválidos');
    }

    if ($metodo === 'POST') {
        $idAsociado = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $valorPrestamo = filter_var($datos['valorPrestamo'] ?? null, FILTER_VALIDATE_FLOAT);
        $tasaInteres = filter_var($datos['tasaInteres'] ?? null, FILTER_VALIDATE_FLOAT);
        $numeroCuotas = filter_var($datos['numeroCuotas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $fechaPrestamo = trim((string)($datos['fechaPrestamo'] ?? ''));

        if (!$idAsociado || $valorPrestamo === false || $valorPrestamo <= 0 || $tasaInteres === false || $tasaInteres < 0 || !$numeroCuotas || $fechaPrestamo === '') {
            http_response_code(400);
            throw new Exception('Complete correctamente todos los campos del préstamo');
        }

        $sql = "INSERT INTO prestamos (idAsociado, valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo)
                VALUES (:idAsociado, :valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':idAsociado' => $idAsociado,
            ':valorPrestamo' => $valorPrestamo,
            ':tasaInteres' => $tasaInteres,
            ':numeroCuotas' => $numeroCuotas,
            ':fechaPrestamo' => $fechaPrestamo
        ]);

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Préstamo registrado correctamente']);
        exit;
    }

    if ($metodo === 'PUT') {
        $idPrestamo = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idAsociado = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $valorPrestamo = filter_var($datos['valorPrestamo'] ?? null, FILTER_VALIDATE_FLOAT);
        $tasaInteres = filter_var($datos['tasaInteres'] ?? null, FILTER_VALIDATE_FLOAT);
        $numeroCuotas = filter_var($datos['numeroCuotas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $fechaPrestamo = trim((string)($datos['fechaPrestamo'] ?? ''));

        if (!$idPrestamo || !$idAsociado || $valorPrestamo === false || $valorPrestamo <= 0 || $tasaInteres === false || $tasaInteres < 0 || !$numeroCuotas || $fechaPrestamo === '') {
            http_response_code(400);
            throw new Exception('Datos incompletos para actualizar el préstamo');
        }

        $sql = "UPDATE prestamos
                SET idAsociado = :idAsociado,
                    valorPrestamo = :valorPrestamo,
                    tasaInteres = :tasaInteres,
                    numeroCuotas = :numeroCuotas,
                    fechaPrestamo = :fechaPrestamo
                WHERE idPrestamo = :idPrestamo";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':idPrestamo' => $idPrestamo,
            ':idAsociado' => $idAsociado,
            ':valorPrestamo' => $valorPrestamo,
            ':tasaInteres' => $tasaInteres,
            ':numeroCuotas' => $numeroCuotas,
            ':fechaPrestamo' => $fechaPrestamo
        ]);

        if ($stmt->rowCount() === 0) {
            // Verifica si existe el préstamo aunque no hayan cambiado valores
            $check = $conexion->prepare("SELECT idPrestamo FROM prestamos WHERE idPrestamo = :idPrestamo");
            $check->execute([':idPrestamo' => $idPrestamo]);
            if (!$check->fetchColumn()) {
                http_response_code(404);
                throw new Exception('No se encontró el préstamo a actualizar');
            }
        }

        echo json_encode(['success' => true, 'message' => 'Préstamo actualizado correctamente']);
        exit;
    }

    if ($metodo === 'DELETE') {
        $idPrestamo = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$idPrestamo) {
            http_response_code(400);
            throw new Exception('Identificador de préstamo inválido');
        }

        $stmt = $conexion->prepare("DELETE FROM prestamos WHERE idPrestamo = :idPrestamo");
        $stmt->execute([':idPrestamo' => $idPrestamo]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            throw new Exception('No se encontró el préstamo a eliminar');
        }

        echo json_encode(['success' => true, 'message' => 'Préstamo eliminado correctamente']);
        exit;
    }

    http_response_code(405);
    throw new Exception('Método HTTP no permitido');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}