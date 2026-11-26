<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    $documento = trim((string)($_SESSION['documento'] ?? ''));
    if ($documento === '') {
        http_response_code(401);
        throw new Exception('Inicia sesión para consultar tus pagos');
    }

    $rol = strtolower(trim((string)($_SESSION['rol'] ?? $_SESSION['Rol'] ?? '')));
    $esAdmin = in_array($rol, ['admin', 'administrador'], true);

    $conexion = (new Conexion())->conectar();

    $consultaAsociado = $conexion->prepare('SELECT idAsociado FROM asociados WHERE documento = :documento LIMIT 1');
    $consultaAsociado->execute([':documento' => $documento]);
    $asociado = $consultaAsociado->fetch(PDO::FETCH_ASSOC);

    if (!$asociado) {
        http_response_code(404);
        throw new Exception('No se encontró el asociado de la sesión');
    }

    $idAsociado = (int)$asociado['idAsociado'];
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        if ($esAdmin) {
            $consultaPrestamos = $conexion->prepare('SELECT p.idPrestamo, p.valorPrestamo, p.numeroCuotas, p.idAsociado,
                                                            a.documento, a.nombres, a.apellidos
                                                     FROM prestamos p
                                                     INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                                                     ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC');
            $consultaPrestamos->execute();

            $consultaPagos = $conexion->prepare('SELECT pg.idPago, pg.valorCuota, pg.fechaPago, pg.idPrestamo,
                                                         a.documento, a.nombres, a.apellidos
                                                 FROM pagos_cuotas pg
                                                 INNER JOIN prestamos p ON p.idPrestamo = pg.idPrestamo
                                                 INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                                                 ORDER BY pg.fechaPago DESC, pg.idPago DESC');
            $consultaPagos->execute();
        } else {
            $consultaPrestamos = $conexion->prepare('SELECT idPrestamo, valorPrestamo, numeroCuotas, idAsociado
                                                     FROM prestamos
                                                     WHERE idAsociado = :idAsociado
                                                     ORDER BY fechaPrestamo DESC, idPrestamo DESC');
            $consultaPrestamos->execute([':idAsociado' => $idAsociado]);

            $consultaPagos = $conexion->prepare('SELECT pg.idPago, pg.valorCuota, pg.fechaPago, pg.idPrestamo
                                                 FROM pagos_cuotas pg
                                                 INNER JOIN prestamos p ON p.idPrestamo = pg.idPrestamo
                                                 WHERE p.idAsociado = :idAsociado
                                                 ORDER BY pg.fechaPago DESC, pg.idPago DESC');
            $consultaPagos->execute([':idAsociado' => $idAsociado]);
        }

        echo json_encode([
            'success' => true,
            'esAdmin' => $esAdmin,
            'prestamos' => $consultaPrestamos->fetchAll(PDO::FETCH_ASSOC),
            'pagos' => $consultaPagos->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit;
    }

    if ($metodo !== 'POST') {
        http_response_code(405);
        throw new Exception('Método HTTP no permitido');
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception('Datos inválidos');
    }

    $idPrestamo = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT);
    $valorCuota = filter_var($datos['valorCuota'] ?? null, FILTER_VALIDATE_FLOAT);
    $fechaPago = trim((string)($datos['fechaPago'] ?? ''));

    if ($idPrestamo === false || $idPrestamo < 1 || $valorCuota === false || $valorCuota <= 0 || $fechaPago === '') {
        http_response_code(400);
        throw new Exception('Complete correctamente el préstamo, el valor y la fecha');
    }

    if ($esAdmin) {
        $consultaPrestamo = $conexion->prepare('SELECT idPrestamo FROM prestamos
                                                WHERE idPrestamo = :idPrestamo
                                                LIMIT 1');
        $consultaPrestamo->execute([':idPrestamo' => $idPrestamo]);

        if (!$consultaPrestamo->fetch()) {
            http_response_code(404);
            throw new Exception('El préstamo seleccionado no existe');
        }
    } else {
        $consultaPrestamo = $conexion->prepare('SELECT idPrestamo FROM prestamos
                                                WHERE idPrestamo = :idPrestamo AND idAsociado = :idAsociado
                                                LIMIT 1');
        $consultaPrestamo->execute([
            ':idPrestamo' => $idPrestamo,
            ':idAsociado' => $idAsociado
        ]);

        if (!$consultaPrestamo->fetch()) {
            http_response_code(403);
            throw new Exception('El préstamo no pertenece a tu cuenta');
        }
    }

    $consultaPago = $conexion->prepare('INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
                                        VALUES (:valorCuota, :fechaPago, :idPrestamo)');
    $consultaPago->execute([
        ':valorCuota' => $valorCuota,
        ':fechaPago' => $fechaPago,
        ':idPrestamo' => $idPrestamo
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Pago registrado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}