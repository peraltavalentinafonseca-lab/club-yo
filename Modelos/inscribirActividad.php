<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    $documento = trim((string)($_SESSION['documento'] ?? ''));
    if ($documento === '') {
        http_response_code(401);
        throw new Exception('Inicia sesión para inscribirte en una actividad');
    }

    $conexion = (new Conexion())->conectar();

    $stmtAsociado = $conexion->prepare('SELECT idAsociado FROM asociados WHERE documento = :documento LIMIT 1');
    $stmtAsociado->execute([':documento' => $documento]);
    $asociado = $stmtAsociado->fetch(PDO::FETCH_ASSOC);

    if (!$asociado) {
        http_response_code(404);
        throw new Exception('No se encontró el asociado de la sesión');
    }

    $idAsociado = (int)$asociado['idAsociado'];
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $stmtInscritas = $conexion->prepare('SELECT idActividad FROM actividades_asociado WHERE idAsociado = :idAsociado');
        $stmtInscritas->execute([':idAsociado' => $idAsociado]);
        $ids = array_map('intval', $stmtInscritas->fetchAll(PDO::FETCH_COLUMN));

        echo json_encode([
            'success' => true,
            'inscritas' => $ids
        ]);
        exit;
    }

    if ($metodo !== 'POST') {
        http_response_code(405);
        throw new Exception('Método HTTP no permitido');
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    $idActividad = filter_var($datos['idActividad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception('Selecciona una actividad válida');
    }

    $stmtAct = $conexion->prepare('SELECT idActividad, nombreActividad FROM actividades WHERE idActividad = :id LIMIT 1');
    $stmtAct->execute([':id' => $idActividad]);
    $act = $stmtAct->fetch(PDO::FETCH_ASSOC);

    if (!$act) {
        http_response_code(404);
        throw new Exception('La actividad no existe');
    }

    $stmtCheck = $conexion->prepare('SELECT idParticipacion FROM actividades_asociado WHERE idActividad = :idAct AND idAsociado = :idAso LIMIT 1');
    $stmtCheck->execute([
        ':idAct' => $idActividad,
        ':idAso' => $idAsociado
    ]);

    if ($stmtCheck->fetch()) {
        http_response_code(409);
        throw new Exception('Ya estás inscrito en esta actividad');
    }

    $stmtInsert = $conexion->prepare('INSERT INTO actividades_asociado (idAsociado, idActividad) VALUES (:idAso, :idAct)');
    $stmtInsert->execute([
        ':idAso' => $idAsociado,
        ':idAct' => $idActividad
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'idParticipacion' => (int)$conexion->lastInsertId(),
        'message' => 'Te has inscrito correctamente en ' . $act['nombreActividad']
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ya estás inscrito en esta actividad']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}