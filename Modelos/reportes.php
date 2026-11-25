<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

try {
    if (!isset($_SESSION['documento'],$_SESSION['rol'])) {
        http_response_code(401);
        throw new Exception('Inicia sesión para consultar los reportes');
    }

    $rol = strtolower((string)$_SESSION['rol']);
    if (!in_array($rol, ['admin', 'administrador'], true)) {
        http_response_code(403);
        throw new Exception('Solo un administrador puede acceder a los reportes');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new Exception('Método HTTP no permitido');
    }

    $conexion = (new Conexion())->conectar();

    // 1. Indicadores generales
    $totalAsociados = (int)$conexion->query("SELECT COUNT(*) FROM asociados")->fetchColumn();

    $cartera =$conexion->query("SELECT COALESCE(SUM(valorPrestamo), 0) AS totalPrestado,
                                        COUNT(*) AS cantidadPrestamos
                                 FROM prestamos")->fetch(PDO::FETCH_ASSOC);

    $totalPagado = (float)$conexion->query("SELECT COALESCE(SUM(valorCuota), 0) FROM pagos_cuotas")->fetchColumn();

    $totalRecaudadoActividades = (float)$conexion->query("SELECT COALESCE(SUM(totalRecaudado), 0) FROM actividades")->fetchColumn();

    // 2. Estado de cuenta y participación por asociado
    $sqlAsociados = "SELECT a.idAsociado,
                            a.documento,
                            CONCAT(a.nombres, ' ', a.apellidos) AS asociado,
                            (
                                SELECT COUNT(*)
                                FROM prestamos pr0
                                WHERE pr0.idAsociado = a.idAsociado
                            ) AS prestamosActivos,
                            COALESCE((
                                SELECT SUM(pr.valorPrestamo)
                                FROM prestamos pr
                                WHERE pr.idAsociado = a.idAsociado
                            ), 0) AS totalPrestado,
                            COALESCE((
                                SELECT SUM(pc.valorCuota)
                                FROM pagos_cuotas pc
                                INNER JOIN prestamos pr2 ON pr2.idPrestamo = pc.idPrestamo
                                WHERE pr2.idAsociado = a.idAsociado
                            ), 0) AS totalAbonado,
                            (
                                SELECT COUNT(*)
                                FROM actividades_asociado aa
                                WHERE aa.idAsociado = a.idAsociado
                            ) AS actividadesParticipadas
                     FROM asociados a
                     ORDER BY a.nombres ASC, a.apellidos ASC";

    $stmtAsociados =$conexion->prepare($sqlAsociados);$stmtAsociados->execute();
    $detalleAsociados =$stmtAsociados->fetchAll(PDO::FETCH_ASSOC);

    // 3. Control detallado de cartera de préstamos
    $sqlPrestamos = "SELECT p.idPrestamo,
                            a.documento,
                            CONCAT(a.nombres, ' ', a.apellidos) AS asociado,
                            p.valorPrestamo,
                            p.tasaInteres,
                            p.numeroCuotas,
                            p.fechaPrestamo,
                            COALESCE(SUM(pc.valorCuota), 0) AS totalPagado
                     FROM prestamos p
                     INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                     LEFT JOIN pagos_cuotas pc ON pc.idPrestamo = p.idPrestamo
                     GROUP BY p.idPrestamo, a.documento, a.nombres, a.apellidos,
                              p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo
                     ORDER BY p.idPrestamo DESC";

    $stmtPrestamos =$conexion->prepare($sqlPrestamos);$stmtPrestamos->execute();
    $carteraPrestamos =$stmtPrestamos->fetchAll(PDO::FETCH_ASSOC);

    // 4. Balance histórico de actividades y distribución
    $sqlActividades = "SELECT act.idActividad,
                              act.nombreActividad,
                              act.fechaEvento,
                              act.municipio,
                              act.totalRecaudado,
                              COUNT(aa.idParticipacion) AS participantes
                       FROM actividades act
                       LEFT JOIN actividades_asociado aa ON aa.idActividad = act.idActividad
                       GROUP BY act.idActividad, act.nombreActividad, act.fechaEvento,
                                act.municipio, act.totalRecaudado
                       ORDER BY act.fechaEvento DESC, act.idActividad DESC";

    $stmtActividades =$conexion->prepare($sqlActividades);$stmtActividades->execute();
    $balanceActividades =$stmtActividades->fetchAll(PDO::FETCH_ASSOC);

    $totalPrestadoNum = (float)($cartera['totalPrestado'] ?? 0);$saldoPendienteGlobal = max(0, $totalPrestadoNum -$totalPagado);

    echo json_encode([
        'success' => true,
        'resumen' => [
            'totalAsociados' => $totalAsociados,
            'cantidadPrestamos' => (int)($cartera['cantidadPrestamos'] ?? 0), 'totalPrestado' =>$totalPrestadoNum,
            'totalPagado' => $totalPagado,
            'saldoPendiente' => $saldoPendienteGlobal,
            'totalRecaudadoActividades' => $totalRecaudadoActividades
        ],
        'asociados' => $detalleAsociados,
        'prestamos' => $carteraPrestamos,
        'actividades' => $balanceActividades
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}