<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class JustificacionWebController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * GET /v1/web/justificaciones
     * Admin ve todas. Supervisor ve solo de sus instituciones.
     */
    public function index(Request $req): void
    {
        $rol    = $_REQUEST['auth_user']['rol'] ?? '';
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $sql    = "SELECT j.*, ua.codigo_empleado, ua.nombres, ua.apellido_paterno,
                          s.nombre AS sede_nombre
                   FROM justificaciones j
                   LEFT JOIN usuarios_app ua ON j.usuario_app_id = ua.id
                   LEFT JOIN sedes s ON j.sede_id = s.id
                   WHERE 1=1";
        $params = [];

        // Supervisor filtra por sus sedes
        if (!in_array($rol, ['super_admin', 'administrador'])) {
            $sql .= " AND j.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $userId;
        }

        if ($req->query('estado'))
            { $sql .= ' AND j.estado = :estado'; $params[':estado'] = $req->query('estado'); }
        if ($req->query('sede_id'))
            { $sql .= ' AND j.sede_id = :sid'; $params[':sid'] = $req->query('sede_id'); }
        if ($req->query('usuario_app_id'))
            { $sql .= ' AND j.usuario_app_id = :uid2'; $params[':uid2'] = $req->query('usuario_app_id'); }

        $sql .= ' ORDER BY j.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/web/justificaciones/{id}/aprobar
     * Al aprobar → actualiza asistencias del período a 'PRESENTE'
     */
    public function aprobar(Request $req): void
    {
        $id   = (int) $req->param('id');
        $obs  = (string) $req->input('observaciones', '');
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $stmt = $this->db->prepare("SELECT * FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado'] !== 'PENDIENTE')
            Response::error('Solo se pueden aprobar justificaciones pendientes', 400);

        // Aprobar
        $stmt = $this->db->prepare("
            UPDATE justificaciones
            SET estado = 'APROBADO', usuario_web_id = :uid,
                observaciones = :obs, fecha_revision = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':uid' => $userId, ':obs' => $obs, ':id' => $id]);

        // Actualizar asistencias del período a PRESENTE
        $stmt = $this->db->prepare("
            UPDATE asistencias
            SET estado_diario = 'PRESENTE',
                observacion   = :obs2
            WHERE usuario_app_id = :uaid
              AND sede_id        = :sid
              AND fecha BETWEEN :fi AND :ff
        ");
        $stmt->execute([
            ':obs2' => 'Justificación Aprobada: ' . $obs,
            ':uaid' => $just['usuario_app_id'],
            ':sid'  => $just['sede_id'],
            ':fi'   => $just['fecha_inicio'],
            ':ff'   => $just['fecha_fin'],
        ]);

        Response::success(null, 'Justificación aprobada correctamente');
    }

    /**
     * POST /v1/web/justificaciones/{id}/rechazar
     * Al rechazar → asistencias del período quedan como 'FALTA'
     */
    public function rechazar(Request $req): void
    {
        $id   = (int) $req->param('id');
        $obs  = (string) $req->input('observaciones', '');
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        if (empty($obs)) Response::unprocessable('Las observaciones son requeridas al rechazar');

        $stmt = $this->db->prepare("SELECT * FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado'] !== 'PENDIENTE')
            Response::error('Solo se pueden rechazar justificaciones pendientes', 400);

        $stmt = $this->db->prepare("
            UPDATE justificaciones
            SET estado = 'RECHAZADO', usuario_web_id = :uid,
                observaciones = :obs, fecha_revision = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':uid' => $userId, ':obs' => $obs, ':id' => $id]);

        // Revertir asistencias a FALTA
        $stmt = $this->db->prepare("
            UPDATE asistencias
            SET estado_diario = 'FALTA', observacion = :obs2
            WHERE usuario_app_id = :uaid
              AND sede_id        = :sid
              AND fecha BETWEEN :fi AND :ff
        ");
        $stmt->execute([
            ':obs2' => 'Justificación rechazada: ' . $obs,
            ':uaid' => $just['usuario_app_id'],
            ':sid'  => $just['sede_id'],
            ':fi'   => $just['fecha_inicio'],
            ':ff'   => $just['fecha_fin'],
        ]);

        Response::success(null, 'Justificación rechazada');
    }

    /** GET /v1/web/justificaciones/{id} */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT * FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();
        if (!$just) Response::notFound('Justificación no encontrada');
        Response::success($just);
    }

    /** DELETE /v1/web/justificaciones/{id} */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT estado FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado'] !== 'PENDIENTE')
            Response::error('Solo se pueden eliminar justificaciones pendientes', 400);

        $stmt = $this->db->prepare("DELETE FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        Response::success(null, 'Justificación eliminada correctamente');
    }
}