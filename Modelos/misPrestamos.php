<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    $documento = trim((string)($_SESSION['documento'] ?? ''));
    if ($documento === '') {
        http_response_code(401);
        throw new Exception('Inicia sesión para consultar tus préstamos');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new Exception('Método HTTP no permitido');
    }

    $conexion = (new Conexion())->conectar();

    $sql = "SELECT p.idPrestamo, p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo
            FROM prestamos p
            INNER JOIN asociados a ON a.idAsociado = p.idAsociado
            WHERE a.documento = :documento
            ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':documento' => $documento]);

    echo json_encode([
        'success' => true,
        'prestamos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}