<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class JustificacionAppController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function userId(): int {
        return (int) ($_REQUEST['auth_user']['sub'] ?? 0);
    }

    /**
     * GET /v1/app/justificaciones
     * El trabajador ve SOLO sus justificaciones.
     */
    public function index(Request $req): void
    {
        $stmt = $this->db->prepare("
            SELECT j.*, s.nombre AS sede_nombre
            FROM justificaciones j
            LEFT JOIN sedes s ON j.sede_id = s.id
            WHERE j.usuario_app_id = :uid
            ORDER BY j.created_at DESC
        ");
        $stmt->execute([':uid' => $this->userId()]);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/app/justificaciones
     * Tipos: ENFERMEDAD, PERMISO_PERSONAL, LICENCIA, COMISION_SERVICIO,
     *        CAPACITACION, DUELO, MATERNIDAD, PATERNIDAD, OLVIDO_MARCACION, OTRO
     */
    public function store(Request $req): void
    {
        $userId  = $this->userId();
        $sedeId  = (int) $req->input('sede_id');
        $tipo    = (string) $req->input('tipo');
        $fInicio = (string) $req->input('fecha_inicio');
        $fFin    = (string) $req->input('fecha_fin');
        $motivo  = (string) $req->input('motivo', '');

        $tipos_validos = [
            'ENFERMEDAD','PERMISO_PERSONAL','LICENCIA','COMISION_SERVICIO',
            'CAPACITACION','DUELO','MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO'
        ];

        $errors = [];
        if (!$sedeId)                         $errors[] = 'sede_id es requerido';
        if (!in_array($tipo, $tipos_validos)) $errors[] = 'tipo inválido';
        if (!$fInicio)                        $errors[] = 'fecha_inicio es requerida';
        if (!$fFin)                           $errors[] = 'fecha_fin es requerida';
        if (!$motivo)                         $errors[] = 'motivo es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar que el trabajador está asignado a esa sede
        $stmt = $this->db->prepare("
            SELECT id FROM usuario_app_sede
            WHERE usuario_app_id = :uid AND sede_id = :sid AND estado = 'ACTIVO'
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        if (!$stmt->fetch()) Response::error('No estás asignado a esa sede', 403);

        $stmt = $this->db->prepare("
            INSERT INTO justificaciones
                (usuario_app_id, sede_id, tipo, fecha_inicio, fecha_fin, motivo, estado, created_at)
            VALUES (:uid, :sid, :tipo, :fi, :ff, :motivo, 'PENDIENTE', NOW())
        ");
        $stmt->execute([
            ':uid'   => $userId,
            ':sid'   => $sedeId,
            ':tipo'  => $tipo,
            ':fi'    => $fInicio,
            ':ff'    => $fFin,
            ':motivo'=> $motivo,
        ]);

        Response::success(
            ['id' => $this->db->lastInsertId()],
            'Justificación enviada. Pendiente de revisión.',
            201
        );
    }

    /** GET /v1/app/justificaciones/{id} */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare(
            "SELECT * FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $this->userId()]);
        $just = $stmt->fetch();
        if (!$just) Response::notFound('Justificación no encontrada');
        Response::success($just);
    }

    /**
     * DELETE /v1/app/justificaciones/{id}
     * Solo se pueden eliminar las que están en estado PENDIENTE.
     */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $uid  = $this->userId();

        $stmt = $this->db->prepare(
            "SELECT estado FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $uid]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado'] !== 'PENDIENTE')
            Response::error('Solo se pueden eliminar justificaciones pendientes', 400);

        $stmt = $this->db->prepare("DELETE FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        Response::success(null, 'Justificación eliminada correctamente');
    }
}