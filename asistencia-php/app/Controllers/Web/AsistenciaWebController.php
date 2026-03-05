<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class AsistenciaWebController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function userId(): int { return (int) ($_REQUEST['auth_user']['sub'] ?? 0); }
    private function rol(): string { return $_REQUEST['auth_user']['rol'] ?? ''; }

    public function index(Request $req): void
    {
        $sql = "
            SELECT ad.id, ad.tipo, ad.marcada_en, ad.distancia_metros,
                   ad.estado_marcacion, ad.motivo_observacion, ad.estado_revision,
                   ad.dentro_rango,
                   a.fecha, a.estado_diario,
                   u.nombres, u.apellido_paterno, u.codigo_empleado,
                   s.nombre AS sede_nombre
            FROM asistencias_diarias ad
            INNER JOIN asistencias a  ON a.id = ad.asistencia_id
            INNER JOIN usuarios_app u ON u.id = a.usuario_app_id
            INNER JOIN sedes s        ON s.id = a.sede_id
            WHERE 1=1
        ";
        $params = [];

        // Supervisor solo ve su sede
        if ($this->rol() === 'supervisor') {
            $sql .= " AND a.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        if ($req->query('sede_id')) {
            $sql .= " AND a.sede_id = :sid";
            $params[':sid'] = (int) $req->query('sede_id');
        }
        if ($req->query('fecha_inicio')) {
            $sql .= " AND DATE(ad.marcada_en) >= :fi";
            $params[':fi'] = $req->query('fecha_inicio');
        }
        if ($req->query('fecha_fin')) {
            $sql .= " AND DATE(ad.marcada_en) <= :ff";
            $params[':ff'] = $req->query('fecha_fin');
        }
        if ($req->query('estado_marcacion')) {
            $sql .= " AND ad.estado_marcacion = :em";
            $params[':em'] = $req->query('estado_marcacion');
        }
        if ($req->query('estado_revision')) {
            $sql .= " AND ad.estado_revision = :er";
            $params[':er'] = $req->query('estado_revision');
        }

        $sql .= " ORDER BY ad.marcada_en DESC LIMIT 200";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /**
     * PATCH /v1/web/asistencias/{id}/revision
     * El admin/supervisor revisa una marcación OBSERVADA.
     * Body: { "estado_revision": "APROBADA" | "MANTENER_OBSERVADA", "observacion": "..." }
     */
    public function updateRevision(Request $req): void
    {
        $id             = (int) $req->param('id');
        $estadoRevision = (string) $req->input('estado_revision');
        $observacion    = (string) $req->input('observacion', '');

        $validos = ['APROBADA', 'MANTENER_OBSERVADA'];
        if (!in_array($estadoRevision, $validos))
            Response::unprocessable('estado_revision inválido. Use: ' . implode(' | ', $validos));

        // Verificar que existe y que el rol tiene acceso
        $stmt = $this->db->prepare("
            SELECT ad.id, a.sede_id
            FROM asistencias_diarias ad
            INNER JOIN asistencias a ON a.id = ad.asistencia_id
            WHERE ad.id = ?
        ");
        $stmt->execute([$id]);
        $marcacion = $stmt->fetch();
        if (!$marcacion) Response::notFound('Marcación no encontrada');

        // Supervisor: solo puede revisar su sede
        if ($this->rol() === 'supervisor') {
            $stmtChk = $this->db->prepare("
                SELECT id FROM usuario_web_sede
                WHERE usuario_web_id = ? AND sede_id = ? AND activo = 1
            ");
            $stmtChk->execute([$this->userId(), $marcacion['sede_id']]);
            if (!$stmtChk->fetch()) Response::error('Sin acceso a esta marcación', 403);
        }

        $this->db->prepare("
            UPDATE asistencias_diarias
            SET estado_revision = ?, revision_observacion = ?, revisado_por = ?, revisado_en = NOW()
            WHERE id = ?
        ")->execute([$estadoRevision, $observacion ?: null, $this->userId(), $id]);

        Response::success(null, 'Revisión guardada correctamente');
    }
}