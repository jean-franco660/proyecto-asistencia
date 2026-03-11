<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class AsistenciaAppController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function userId(): int {
        return (int) ($_REQUEST['auth_user']['sub'] ?? 0);
    }

    /*
     * POST /v1/app/asistencia
     */

    public function store(Request $req): void
    {
        $userId    = $this->userId();
        $sedeId    = (int) $req->input('sede_id');
        $tipo      = (string) $req->input('tipo');
        $fechaHora = (string) $req->input('fecha_hora');
        $latitud   = (float)  $req->input('latitud');
        $longitud  = (float)  $req->input('longitud');
        $offlineUuid = $req->input('offline_uuid');

        if (!$sedeId || !in_array($tipo, ['ENTRADA','SALIDA']) || !$fechaHora || !$latitud || !$longitud)
            Response::unprocessable('Faltan campos requeridos');

        // Idempotencia para sync offline
        if ($offlineUuid) {
            $stmt = $this->db->prepare("SELECT id FROM asistencias_diarias WHERE offline_uuid = :uuid");
            $stmt->execute([':uuid' => $offlineUuid]);
            if ($stmt->fetch())
                Response::success(null, 'Marcación ya registrada (idempotente)');
        }

        // Verificar asignación a sede
        $stmt = $this->db->prepare("
            SELECT uas.*, hs.hora_entrada, hs.hora_salida,
                hs.tolerancia_entrada_minutos, hs.tolerancia_salida_minutos
            FROM usuario_app_sede uas
            INNER JOIN horarios_sede hs ON uas.horario_sede_id = hs.id
            WHERE uas.usuario_app_id = :uid AND uas.sede_id = :sid AND uas.estado = 'ACTIVO'
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        $asignacion = $stmt->fetch();

        if (!$asignacion)
            Response::error('No estás asignado a esta sede o no tienes horario asignado.', 403);

        // Validar GPS
        $stmt = $this->db->prepare("SELECT latitud, longitud, radio FROM sedes WHERE id = :sid");
        $stmt->execute([':sid' => $sedeId]);
        $sede = $stmt->fetch();

        $distancia = $this->calcularDistanciaMetros($latitud, $longitud, $sede['latitud'], $sede['longitud']);
        if ($distancia > $sede['radio'])
            Response::error(
                "Debes estar dentro de la sede para registrar asistencia.",
                403,
                ['distancia_metros' => (int) $distancia, 'radio_sede' => $sede['radio']]
            );

        // Evaluar ventana horaria
        $marcadaEn    = new \DateTime($fechaHora);
        $fechaDia     = $marcadaEn->format('Y-m-d');
        $estadoMarcacion  = 'VALIDA';
        $motivoObservacion = null;
        $estadoDiario  = 'PRESENTE';
        $minutosTarde  = 0;

        if ($tipo === 'ENTRADA') {
            $horaEntrada = new \DateTime("$fechaDia {$asignacion['hora_entrada']}");
            $limiteConTolerancia = (clone $horaEntrada)->modify("+{$asignacion['tolerancia_entrada_minutos']} minutes");

            if ($marcadaEn < $horaEntrada) {
                $estadoMarcacion   = 'OBSERVADA';
                $motivoObservacion = 'FUERA_DE_HORARIO';
            } elseif ($marcadaEn > $limiteConTolerancia) {
                $minutosTarde  = (int) (($marcadaEn->getTimestamp() - $horaEntrada->getTimestamp()) / 60);
                $estadoDiario  = 'TARDANZA';
            }
        } elseif ($tipo === 'SALIDA') {
            $horaSalida    = new \DateTime("$fechaDia {$asignacion['hora_salida']}");
            $limiteInicio  = (clone $horaSalida)->modify("-{$asignacion['tolerancia_salida_minutos']} minutes");

            if ($marcadaEn < $limiteInicio || $marcadaEn > $horaSalida) {
                $estadoMarcacion   = 'OBSERVADA';
                $motivoObservacion = 'FUERA_DE_HORARIO';
            }
        }

        $this->db->beginTransaction();
        try {
            // Obtener o crear cabecera de asistencia
            $stmt = $this->db->prepare("
                SELECT id FROM asistencias
                WHERE usuario_app_id = :uid AND sede_id = :sid AND fecha = :fecha
            ");
            $stmt->execute([':uid' => $userId, ':sid' => $sedeId, ':fecha' => $fechaDia]);
            $asistencia = $stmt->fetch();

            if (!$asistencia) {
                $stmt = $this->db->prepare("
                    INSERT INTO asistencias (usuario_app_id, sede_id, horario_sede_id, fecha, estado_diario)
                    VALUES (:uid, :sid, :hid, :fecha, 'PENDIENTE')
                ");
                $stmt->execute([
                    ':uid'   => $userId,
                    ':sid'   => $sedeId,
                    ':hid'   => $asignacion['horario_sede_id'],
                    ':fecha' => $fechaDia,
                ]);
                $asistenciaId = (int) $this->db->lastInsertId();
            } else {
                $asistenciaId = $asistencia['id'];
            }

            // VALIDACIÓN: Prevenir duplicados ENTRADA/SALIDA
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM asistencias_diarias
                WHERE asistencia_id = :aid AND tipo = :tipo
            ");
            $stmt->execute([':aid' => $asistenciaId, ':tipo' => $tipo]);
            
            if ((int)$stmt->fetchColumn() > 0) {
                $this->db->rollBack();
                Response::error(
                    "Ya has marcado {$tipo} hoy en esta sede.",
                    400,
                    ['tipo' => $tipo]
                );
            }

            // Guardar marcación
            $stmt = $this->db->prepare("
                INSERT INTO asistencias_diarias
                    (asistencia_id, tipo, marcada_en, latitud, longitud,
                    dentro_rango, distancia_metros, estado_marcacion, motivo_observacion,
                    estado_revision, offline_uuid, registrado_en)
                VALUES
                    (:aid, :tipo, :marcada, :lat, :lng,
                    1, :dist, :estado, :motivo,
                    :revision, :uuid, 'APP_ONLINE')
            ");
            $stmt->execute([
                ':aid'      => $asistenciaId,
                ':tipo'     => $tipo,
                ':marcada'  => $fechaHora,
                ':lat'      => $latitud,
                ':lng'      => $longitud,
                ':dist'     => (int) $distancia,
                ':estado'   => $estadoMarcacion,
                ':motivo'   => $motivoObservacion,
                ':revision' => $estadoMarcacion === 'OBSERVADA' ? 'PENDIENTE' : 'APROBADA',
                ':uuid'     => $offlineUuid,
            ]);

            // Actualizar estado diario de la cabecera
            if ($tipo === 'ENTRADA' && $estadoDiario !== 'PENDIENTE') {
                $this->db->prepare("
                    UPDATE asistencias
                    SET estado_diario = :ed, hora_entrada = :he, minutos_tarde = :mt
                    WHERE id = :id
                ")->execute([
                    ':ed' => $estadoDiario,
                    ':he' => $marcadaEn->format('H:i:s'),
                    ':mt' => $minutosTarde,
                    ':id' => $asistenciaId,
                ]);
            } elseif ($tipo === 'SALIDA') {
                // Actualizar estado_diario basado en SALIDA
                $nuevoEstado = $estadoMarcacion === 'OBSERVADA' ? 'OBSERVADA' : 'PRESENTE';
                $this->db->prepare("
                    UPDATE asistencias
                    SET hora_salida = :hs,
                        estado_diario = CASE 
                            WHEN estado_diario = 'TARDANZA' THEN 'TARDANZA'
                            ELSE :estado
                        END
                    WHERE id = :id
                ")->execute([
                    ':hs'     => $marcadaEn->format('H:i:s'),
                    ':estado' => $nuevoEstado,
                    ':id'     => $asistenciaId,
                ]);
            }

            $this->db->commit();

            Response::success([
                'tipo'              => $tipo,
                'dentro_rango'      => true,
                'distancia_metros'  => (int) $distancia,
                'estado_marcacion'  => $estadoMarcacion,
                'motivo_observacion'=> $motivoObservacion,
                'estado_diario'     => $estadoDiario,
            ], 'Asistencia registrada correctamente', 201);

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[AsistenciaAppController::store] Error: ' . $e->getMessage());
            Response::error('Error registrando asistencia. Intente nuevamente.', 500);
        }
    }

    /**
     * Calcula la distancia en metros entre dos puntos GPS (fórmula Haversine)
     */
    private function calcularDistanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Radio de la Tierra en metros
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ/2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ/2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** GET /v1/app/asistencia/{id} — historial del trabajador */
    public function historial(Request $req): void
    {
        $userId = $this->userId();
        $solicitadoId = (int) $req->param('usuarioId');

        // Solo puede ver su propio historial
        if ($solicitadoId !== $userId)
            Response::forbidden('Solo puedes ver tu propio historial');

        $stmt = $this->db->prepare("
            SELECT a.*, s.nombre AS sede_nombre,
                   hs.nombre_turno, hs.hora_entrada, hs.hora_salida
            FROM asistencias a
            LEFT JOIN sedes s       ON a.sede_id = s.id
            LEFT JOIN horarios_sede hs ON a.horario_sede_id = hs.id
            WHERE a.usuario_app_id = :uid
            ORDER BY a.fecha DESC
            LIMIT 60
        ");
        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/app/estado-dia?sede_id=1 — estado actual del trabajador hoy */
    public function estadoDia(Request $req): void
    {
        $userId = $this->userId();
        $sedeId = (int) $req->query('sede_id');
        $hoy    = date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT a.*, hs.hora_entrada, hs.hora_salida, hs.nombre_turno,
                   hs.tolerancia_entrada_minutos
            FROM asistencias a
            LEFT JOIN horarios_sede hs ON a.horario_sede_id = hs.id
            WHERE a.usuario_app_id = :uid AND a.sede_id = :sid AND a.fecha = :fecha
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId, ':fecha' => $hoy]);
        $asistencia = $stmt->fetch();

        // Marcaciones de hoy
        $marcaciones = [];
        if ($asistencia) {
            $stmt2 = $this->db->prepare("
                SELECT tipo, marcada_en, estado_marcacion FROM asistencias_diarias
                WHERE asistencia_id = :aid ORDER BY marcada_en ASC
            ");
            $stmt2->execute([':aid' => $asistencia['id']]);
            $marcaciones = $stmt2->fetchAll();
        }

        $tieneEntrada = in_array('ENTRADA', array_column($marcaciones, 'tipo'));
        $tieneSalida  = in_array('SALIDA', array_column($marcaciones, 'tipo'));

        Response::success([
            'server_now'     => date('c'),
            'tiene_entrada'  => $tieneEntrada,
            'tiene_salida'   => $tieneSalida,
            'next_action'    => !$tieneEntrada ? 'ENTRADA' : (!$tieneSalida ? 'SALIDA' : null),
            'estado_diario'  => $asistencia['estado_diario'] ?? 'SIN_REGISTRO',
            'horario'        => $asistencia ? [
                'nombre_turno'  => $asistencia['nombre_turno'],
                'hora_entrada'  => $asistencia['hora_entrada'],
                'hora_salida'   => $asistencia['hora_salida'],
            ] : null,
            'marcaciones'    => $marcaciones,
        ]);
    }
}