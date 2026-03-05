<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class StatsController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function userId(): int { return (int) ($_REQUEST['auth_user']['sub'] ?? 0); }
    private function rol(): string { return $_REQUEST['auth_user']['rol'] ?? ''; }

    /**
     * GET /v1/web/stats/dashboard
     * Resumen del día de hoy para el panel principal.
     * Filtros: ?sede_id=1&fecha=2025-03-04
     */
    public function dashboard(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $fecha  = $req->query('fecha', date('Y-m-d'));

        $whereSedeAdmin = '1=1';
        $params         = [':fecha' => $fecha];

        // Supervisor: solo sus sedes
        if ($this->rol() === 'supervisor') {
            $whereSedeAdmin = "a.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        if ($sedeId) {
            $whereSedeAdmin .= ' AND a.sede_id = :sid';
            $params[':sid'] = (int) $sedeId;
        }

        // Totales del día
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(estado_diario = 'PRESENTE')   AS presentes,
                SUM(estado_diario = 'TARDANZA')   AS tardanzas,
                SUM(estado_diario = 'FALTA')      AS faltas,
                SUM(estado_diario = 'JUSTIFICADO') AS justificados
            FROM asistencias a
            WHERE {$whereSedeAdmin} AND a.fecha = :fecha
        ");
        $stmt->execute($params);
        $totales = $stmt->fetch();

        // Marcaciones observadas pendientes
        $stmt2 = $this->db->prepare("
            SELECT COUNT(*) AS observadas_pendientes
            FROM asistencias_diarias ad
            INNER JOIN asistencias a ON a.id = ad.asistencia_id
            WHERE {$whereSedeAdmin}
              AND ad.estado_marcacion = 'OBSERVADA'
              AND ad.estado_revision  = 'PENDIENTE'
              AND a.fecha = :fecha
        ");
        $stmt2->execute($params);
        $observadas = $stmt2->fetch();

        // Justificaciones pendientes
        $stmt3 = $this->db->prepare("
            SELECT COUNT(*) AS justificaciones_pendientes
            FROM justificaciones j
            WHERE j.estado = 'PENDIENTE'
              " . ($sedeId ? "AND j.sede_id = :sid2" : "") . "
        ");
        $params3 = $sedeId ? [':sid2' => (int) $sedeId] : [];
        $stmt3->execute($params3);
        $justPendientes = $stmt3->fetch();

        Response::success([
            'fecha'                    => $fecha,
            'presentes'                => (int) $totales['presentes'],
            'tardanzas'                => (int) $totales['tardanzas'],
            'faltas'                   => (int) $totales['faltas'],
            'justificados'             => (int) $totales['justificados'],
            'total_registrados'        => (int) $totales['total'],
            'observadas_pendientes'    => (int) $observadas['observadas_pendientes'],
            'justificaciones_pendientes' => (int) $justPendientes['justificaciones_pendientes'],
        ]);
    }
}