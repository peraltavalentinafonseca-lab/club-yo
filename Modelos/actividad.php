<?php

require_once __DIR__ . '/../Config/conexion.php';

class Actividad
{
    private $conexion;

    public function __construct()
    {
        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
    }

    public function calcularReparto(float $totalRecaudado, int $cantidadParticipantes = 0): array
    {
        $total = round($totalRecaudado, 2);
        $fondoClub = round($total * 0.5, 2);
        $bolsaParticipantes = round($total - $fondoClub, 2);
        $valorPorParticipante = $cantidadParticipantes > 0
            ? round($bolsaParticipantes / $cantidadParticipantes, 2)
            : 0.0;

        return [
            'totalRecaudado' => $total,
            'fondoClub' => $fondoClub,
            'bolsaParticipantes' => $bolsaParticipantes,
            'cantidadParticipantes' => $cantidadParticipantes,
            'valorPorParticipante' => $valorPorParticipante
        ];
    }

    public function obtenerActividades(): array
    {
        $sql = "SELECT a.idActividad,
                       a.nombreActividad,
                       a.fechaEvento,
                       a.municipio,
                       a.totalRecaudado,
                       COUNT(aa.idParticipacion) AS cantidadParticipantes
                FROM actividades a
                LEFT JOIN actividades_asociado aa ON aa.idActividad = a.idActividad
                GROUP BY a.idActividad, a.nombreActividad, a.fechaEvento, a.municipio, a.totalRecaudado
                ORDER BY a.fechaEvento ASC, a.idActividad ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $participantes = (int)($fila['cantidadParticipantes'] ?? 0);
            $reparto = $this->calcularReparto((float)($fila['totalRecaudado'] ?? 0), $participantes);
            $fila['cantidadParticipantes'] = $participantes;
            $fila['fondoClub'] = $reparto['fondoClub'];
            $fila['bolsaParticipantes'] = $reparto['bolsaParticipantes'];
            $fila['valorPorParticipante'] = $reparto['valorPorParticipante'];
        }
        unset($fila);

        return $filas;
    }

    public function obtenerParticipantesPorActividad(int $idActividad): array
    {
        $sql = "SELECT aa.idParticipacion,
                       asoc.idAsociado,
                       asoc.documento,
                       asoc.nombres,
                       asoc.apellidos,
                       CONCAT(asoc.nombres, ' ', asoc.apellidos) AS nombreCompleto
                FROM actividades_asociado aa
                INNER JOIN asociados asoc ON asoc.idAsociado = aa.idAsociado
                WHERE aa.idActividad = :idActividad
                ORDER BY asoc.nombres ASC, asoc.apellidos ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idActividad' => $idActividad]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrarParticipacion(int $idActividad, int $idAsociado): bool
    {
        $check = $this->conexion->prepare(
            "SELECT idParticipacion
             FROM actividades_asociado
             WHERE idActividad = :idActividadCheck AND idAsociado = :idAsociadoCheck"
        );
        $check->execute([
            ':idActividadCheck' => $idActividad,
            ':idAsociadoCheck' => $idAsociado
        ]);

        if ($check->fetchColumn()) {
            return true;
        }

        $sql = "INSERT INTO actividades_asociado (idActividad, idAsociado)
                VALUES (:idActividad, :idAsociado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':idActividad' => $idActividad,
            ':idAsociado' => $idAsociado
        ]);
    }

    public function eliminarParticipacion(int $idParticipacion): bool
    {
        $sql = "DELETE FROM actividades_asociado WHERE idParticipacion = :idParticipacion";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idParticipacion' => $idParticipacion]);
        return $consulta->rowCount() > 0;
    }

    public function guardar(array $datos): bool
    {
        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio, totalRecaudado)
                VALUES (:nombreActividad, :fechaEvento, :municipio, :totalRecaudado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ":nombreActividad" => $datos['nombreActividad'],
            ":fechaEvento" => $datos['fechaEvento'],
            ":municipio" => $datos['municipio'],
            ":totalRecaudado" => $datos['totalRecaudado'] ?? 0,
        ]);
    }

    public function eliminar(int $idActividad): bool
    {
        $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idActividad' => $idActividad]);
        return $consulta->rowCount() > 0;
    }

    public function editar(array $datos): bool
    {
        $sql = "UPDATE actividades
                SET nombreActividad = :nombreActividad,
                    fechaEvento = :fechaEvento,
                    municipio = :municipio,
                    totalRecaudado = :totalRecaudado
                WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $ejecutado = $consulta->execute([
            ":nombreActividad" => $datos['nombreActividad'],
            ":fechaEvento" => $datos['fechaEvento'],
            ":municipio" => $datos['municipio'],
            ":totalRecaudado" => $datos['totalRecaudado'] ?? 0,
            ":idActividad" => $datos['idActividad']
        ]);

        if (!$ejecutado) {
            return false;
        }

        if ($consulta->rowCount() > 0) {
            return true;
        }

        $verificar = $this->conexion->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad");
        $verificar->execute([':idActividad' => $datos['idActividad']]);
        return (bool)$verificar->fetchColumn();
    }
}