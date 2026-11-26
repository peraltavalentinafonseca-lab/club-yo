<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

function leerDatos(): array {
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true);
    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

function validarActividad(array $datos): array {
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = trim((string)($datos['fechaEvento'] ?? ''));
    $municipio = $datos['municipio'] ?? null;

    // Opcional: si envías total recaudado desde el formulario/API
    $recaudoRaw = $datos['totalRecaudado'] ?? $datos['recaudo'] ?? 0;
    $totalRecaudado = filter_var($recaudoRaw, FILTER_VALIDATE_FLOAT);
    if ($totalRecaudado === false || $totalRecaudado < 0) {
        http_response_code(400);
        throw new Exception("Ingrese un total recaudado válido (mayor o igual a 0)");
    }

    if (!is_string($nombre) || trim($nombre) === "" || mb_strlen(trim($nombre)) > 100 ||
        !is_string($municipio) || trim($municipio) === "" || mb_strlen(trim($municipio)) > 50) {
        http_response_code(400);
        throw new Exception("Ingrese un nombre y municipio válidos");
    }

    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        http_response_code(400);
        throw new Exception("Ingrese una fecha de evento válida (AAAA-MM-DD)");
    }

    $montoClub = round($totalRecaudado * 0.5, 2);
    $montoParticipantes = round($totalRecaudado - $montoClub, 2);

    return [
        "nombreActividad" => trim($nombre),
        "fechaEvento" => $fecha,
        "municipio" => trim($municipio),
        "totalRecaudado" => round((float)$totalRecaudado, 2),
        "montoClub" => $montoClub,
        "montoParticipantes" => $montoParticipantes
    ];
}

function validarId($id): int {
    $idActividad = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es válido");
    }
    return (int)$idActividad;
}

try {
    $documentoSesion = trim((string)($_SESSION["documento"] ?? ""));
    $rolSesion = strtolower(trim((string)($_SESSION["Rol"] ?? $_SESSION["rol"] ?? "")));

    if ($documentoSesion === "" || $rolSesion === "") {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar las actividades");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    if ($metodo !== "GET" && !in_array($rolSesion, ["admin", "administrador"], true)) {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/actividad.php";
    $actividad = new Actividad();

    switch ($metodo) {
        case "GET":
            $actividades = $actividad->obtenerActividades();
            // Si cada actividad trae total_recaudado / totalRecaudado y cantidad_participantes, anexamos el desglose 50/50
            foreach ($actividades as &$item) {
                $total = (float)($item['totalRecaudado'] ?? $item['total_recaudado'] ?? 0);
                $participantes = (int)($item['cantidadParticipantes'] ?? $item['total_participantes'] ?? 0);
                $fondoClub = round($total * 0.5, 2);
                $bolsaParticipantes = round($total - $fondoClub, 2);
                $item['fondoClub'] = $fondoClub;
                $item['bolsaParticipantes'] = $bolsaParticipantes;
                $item['valorPorParticipante'] = $participantes > 0 ? round($bolsaParticipantes / $participantes, 2) : 0.0;
            }
            unset($item);

            $resultado = [
                "success" => true,
                "rol" => $rolSesion,
                "actividades" => $actividades
            ];
            http_response_code(200);
            break;

        case "POST":
            $datos = validarActividad(leerDatos());
            $actividad->guardar($datos);
            $resultado = [
                "success" => true,
                "message" => "Actividad creada exitosamente",
                "reparto" => [
                    "totalRecaudado" => $datos["totalRecaudado"],
                    "fondoClub" => $datos["montoClub"],
                    "bolsaParticipantes" => $datos["montoParticipantes"]
                ]
            ];
            http_response_code(201);
            break;

        case "PUT":
            $datosEntrada = leerDatos();
            $idValidado = validarId($datosEntrada["idActividad"] ?? null);
            $datos = array_merge(["idActividad" => $idValidado], validarActividad($datosEntrada));

            if (!$actividad->editar($datos)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad");
            }
            $resultado = [
                "success" => true,
                "message" => "Actividad actualizada exitosamente",
                "reparto" => [
                    "totalRecaudado" => $datos["totalRecaudado"],
                    "fondoClub" => $datos["montoClub"],
                    "bolsaParticipantes" => $datos["montoParticipantes"]
                ]
            ];
            http_response_code(200);
            break;

        case "DELETE":
            $datos = leerDatos();
            $idActividad = validarId($datos["idActividad"] ?? null);

            if (!$actividad->eliminar($idActividad)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad");
            }
            $resultado = [
                "success" => true,
                "message" => "Actividad eliminada correctamente"
            ];
            http_response_code(200);
            break;
    }

    echo json_encode($resultado);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}