<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../Config/conexion.php';

function ejecutarConsultaEspecifica(PDO $conexion, int $numeroConsulta, array $params): array
{
    switch ($numeroConsulta) {
        // 1. Listar todos los datos de los asociados pertenecientes al Club
        case 1:
            $stmt = $conexion->query("SELECT * FROM asociados ORDER BY nombres ASC, apellidos ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Listar los datos de las Mujeres pertenecientes al Club
        case 2:
            // Soporta valores comunes como 'F', 'femenino', 'mujer'
            $sql = "SELECT * FROM asociados
                    WHERE LOWER(TRIM(COALESCE(genero, sexo, ''))) IN ('f', 'femenino', 'mujer')
                    ORDER BY nombres ASC, apellidos ASC";
            $stmt = $conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Imprimir la Cantidad de préstamos realizados
        case 3:
            $cantidad = (int)$conexion->query("SELECT COUNT(*) FROM prestamos")->fetchColumn();
            return ['cantidadPrestamos' => $cantidad];

        // 4. Listar fecha de préstamo, valor de préstamo y Nombres y apellidos del asociado de todos los prestamos realizados
        case 4:
            $sql = "SELECT p.idPrestamo,
                           p.fechaPrestamo,
                           p.valorPrestamo,
                           a.nombres,
                           a.apellidos,
                           CONCAT(a.nombres, ' ', a.apellidos) AS nombreCompleto
                    FROM prestamos p
                    INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                    ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";
            return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // 5. Listar fecha de préstamo, valor de préstamo y Nombres y apellidos del asociado de los prestamos realizados en un rango de fechas
        case 5:
            $fechaInicio = trim((string)($params['fecha_inicio'] ?? ''));
            $fechaFin = trim((string)($params['fecha_fin'] ?? ''));
            if ($fechaInicio === '' || $fechaFin === '') {
                http_response_code(400);
                throw new Exception('Debe indicar fecha_inicio y fecha_fin');
            }
            $sql = "SELECT p.idPrestamo,
                           p.fechaPrestamo,
                           p.valorPrestamo,
                           a.nombres,
                           a.apellidos,
                           CONCAT(a.nombres, ' ', a.apellidos) AS nombreCompleto
                    FROM prestamos p
                    INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                    WHERE p.fechaPrestamo BETWEEN :fechaInicio AND :fechaFin
                    ORDER BY p.fechaPrestamo ASC, p.idPrestamo ASC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':fechaInicio' => $fechaInicio,
                ':fechaFin' => $fechaFin
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Listado de los asociados que tienen residencia en la ciudad de Sogamoso
        case 6:
            $sql = "SELECT * FROM asociados
                    WHERE LOWER(TRIM(COALESCE(ciudadResidencia, direccionResidencia, direccion, ''))) LIKE '%sogamoso%'
                    ORDER BY nombres ASC, apellidos ASC";
            return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // 7. Listado de préstamos realizados a un asociado especifico buscado por número de cedula
        case 7:
            $documento = trim((string)($params['documento'] ?? ''));
            if ($documento === '') {
                http_response_code(400);
                throw new Exception('Debe indicar el número de cédula (documento)');
            }
            $sql = "SELECT p.*,
                           a.documento,
                           a.nombres,
                           a.apellidos,
                           CONCAT(a.nombres, ' ', a.apellidos) AS nombreCompleto
                    FROM prestamos p
                    INNER JOIN asociados a ON a.idAsociado = p.idAsociado
                    WHERE a.documento = :documento
                    ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':documento' => $documento]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 8. Total de dinero que ha pagado un asociado especifico buscado por número de cedula
        case 8:
            $documento = trim((string)($params['documento'] ?? ''));
            if ($documento === '') {
                http_response_code(400);
                throw new Exception('Debe indicar el número de cédula (documento)');
            }
            $sql = "SELECT a.idAsociado,
                           a.documento,
                           a.nombres,
                           a.apellidos,
                           COALESCE(SUM(pc.valorCuota), 0) AS totalPagado
                    FROM asociados a
                    LEFT JOIN prestamos p ON p.idAsociado = a.idAsociado
                    LEFT JOIN pagos_cuotas pc ON pc.idPrestamo = p.idPrestamo
                    WHERE a.documento = :documento
                    GROUP BY a.idAsociado, a.documento, a.nombres, a.apellidos";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':documento' => $documento]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila) {
                http_response_code(404);
                throw new Exception('No se encontró un asociado con esa cédula');
            }
            $fila['totalPagado'] = (float)$fila['totalPagado'];
            return $fila;

        // 9. Cantidad de actividades organizadas por el club en el 2018
        case 9:
            $sql = "SELECT COUNT(*) AS cantidadActividades2018
                    FROM actividades
                    WHERE YEAR(fechaEvento) = 2018";
            $cantidad = (int)$conexion->query($sql)->fetchColumn();
            return ['anio' => 2018, 'cantidadActividades' => $cantidad];

        // 10. Listar Fecha de evento, Nombre de la actividad y total recaudado de todas las actividades organizadas en el club
        case 10:
            $sql = "SELECT idActividad,
                           fechaEvento,
                           nombreActividad,
                           totalRecaudado,
                           ROUND(totalRecaudado * 0.5, 2) AS fondoClub,
                           ROUND(totalRecaudado * 0.5, 2) AS bolsaParticipantes
                    FROM actividades
                    ORDER BY fechaEvento ASC, idActividad ASC";
            return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // 11. Número de actividades en las que ha participado un asociado especifico buscado por número de cedula
        case 11:
            $documento = trim((string)($params['documento'] ?? ''));
            if ($documento === '') {
                http_response_code(400);
                throw new Exception('Debe indicar el número de cédula (documento)');
            }
            $sql = "SELECT a.idAsociado,
                           a.documento,
                           a.nombres,
                           a.apellidos,
                           COUNT(aa.idParticipacion) AS numeroActividades
                    FROM asociados a
                    LEFT JOIN actividades_asociado aa ON aa.idAsociado = a.idAsociado
                    WHERE a.documento = :documento
                    GROUP BY a.idAsociado, a.documento, a.nombres, a.apellidos";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':documento' => $documento]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila) {
                http_response_code(404);
                throw new Exception('No se encontró un asociado con esa cédula');
            }
            $fila['numeroActividades'] = (int)$fila['numeroActividades'];
            return $fila;

        // 12. Valor de la Cuotas y fechas en las que fueron pagadas de un préstamo especifico buscado por código
        case 12:
            $idPrestamo = filter_var($params['idPrestamo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($idPrestamo === false) {
                http_response_code(400);
                throw new Exception('Debe indicar un código de préstamo (idPrestamo) válido');
            }
            $sql = "SELECT idPago,
                           idPrestamo,
                           valorCuota,
                           fechaPago
                    FROM pagos_cuotas
                    WHERE idPrestamo = :idPrestamo
                    ORDER BY fechaPago ASC, idPago ASC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':idPrestamo' => $idPrestamo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 13. Listar datos de las actividades realizadas en el municipio de Paipa
        case 13:
            $sql = "SELECT * FROM actividades
                    WHERE LOWER(TRIM(COALESCE(municipio, ''))) LIKE '%paipa%'
                    ORDER BY fechaEvento ASC, idActividad ASC";
            return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // 14. Buscar una actividad por Nombre y listar los datos de los asociados que participaron en esta
        case 14:
            $nombreActividad = trim((string)($params['nombreActividad'] ?? ''));
            if ($nombreActividad === '') {
                http_response_code(400);
                throw new Exception('Debe indicar nombreActividad para buscar');
            }
            $sql = "SELECT act.idActividad,
                           act.nombreActividad,
                           act.fechaEvento,
                           act.municipio,
                           act.totalRecaudado,
                           asoc.*
                    FROM actividades act
                    INNER JOIN actividades_asociado aa ON aa.idActividad = act.idActividad
                    INNER JOIN asociados asoc ON asoc.idAsociado = aa.idAsociado
                    WHERE LOWER(act.nombreActividad) LIKE LOWER(:nombreActividad)
                    ORDER BY act.fechaEvento ASC, asoc.nombres ASC, asoc.apellidos ASC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':nombreActividad' => '%' . $nombreActividad . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        default:
            http_response_code(400);
            throw new Exception('Número de consulta no válido (use del 1 al 14)');
    }
}

try {
    if (!isset($_SESSION['documento'], $_SESSION['rol'])) {
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

    // Si se solicita una consulta puntual (?consulta=1..14)
    if (isset($_GET['consulta']) && trim((string)$_GET['consulta']) !== '') {
        $num = filter_var($_GET['consulta'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 14]]);
        if ($num === false) {
            http_response_code(400);
            throw new Exception('El parámetro consulta debe ser un número entre 1 y 14');
        }

        $datosConsulta = ejecutarConsultaEspecifica($conexion, (int)$num, $_GET);
        echo json_encode([
            'success' => true,
            'consulta' => (int)$num,
            'data' => $datosConsulta
        ]);
        exit;
    }

    // 1. Indicadores generales
    $totalAsociados = (int)$conexion->query("SELECT COUNT(*) FROM asociados")->fetchColumn();

    $cartera = $conexion->query("SELECT COALESCE(SUM(valorPrestamo), 0) AS totalPrestado,
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

    $stmtAsociados = $conexion->prepare($sqlAsociados);
    $stmtAsociados->execute();
    $detalleAsociados = $stmtAsociados->fetchAll(PDO::FETCH_ASSOC);

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

    $stmtPrestamos = $conexion->prepare($sqlPrestamos);
    $stmtPrestamos->execute();
    $carteraPrestamos = $stmtPrestamos->fetchAll(PDO::FETCH_ASSOC);

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

    $stmtActividades = $conexion->prepare($sqlActividades);
    $stmtActividades->execute();
    $balanceActividades = $stmtActividades->fetchAll(PDO::FETCH_ASSOC);

    $totalPrestadoNum = (float)($cartera['totalPrestado'] ?? 0);
    $saldoPendienteGlobal = max(0, $totalPrestadoNum - $totalPagado);

    echo json_encode([
        'success' => true,
        'resumen' => [
            'totalAsociados' => $totalAsociados,
            'cantidadPrestamos' => (int)($cartera['cantidadPrestamos'] ?? 0),
            'totalPrestado' => $totalPrestadoNum,
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