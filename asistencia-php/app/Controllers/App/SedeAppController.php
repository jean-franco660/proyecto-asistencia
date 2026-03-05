<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class SedeAppController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * GET /v1/app/sedes
     * Devuelve solo las sedes a las que el trabajador está asignado (ACTIVO).
     */
    public function index(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT s.id, s.nombre, s.direccion, s.latitud, s.longitud, s.radio,
                   hs.nombre_turno, hs.hora_entrada, hs.hora_salida,
                   uas.cargo
            FROM usuario_app_sede uas
            INNER JOIN sedes s          ON s.id = uas.sede_id
            LEFT JOIN  horarios_sede hs ON hs.id = uas.horario_sede_id
            WHERE uas.usuario_app_id = :uid AND uas.estado = 'ACTIVO'
            ORDER BY s.nombre
        ");
        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }

    /**
     * GET /v1/app/sedes/{id}
     * Detalle de una sede (solo si el trabajador está asignado).
     */
    public function show(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $sedeId = (int) $req->param('id');

        $stmt = $this->db->prepare("
            SELECT s.*, hs.nombre_turno, hs.hora_entrada, hs.hora_salida,
                   hs.tolerancia_entrada_minutos, hs.tolerancia_salida_minutos
            FROM usuario_app_sede uas
            INNER JOIN sedes s          ON s.id = uas.sede_id
            LEFT JOIN  horarios_sede hs ON hs.id = uas.horario_sede_id
            WHERE uas.usuario_app_id = :uid
              AND uas.sede_id = :sid
              AND uas.estado = 'ACTIVO'
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        $sede = $stmt->fetch();
        if (!$sede) Response::notFound('No estás asignado a esta sede');
        Response::success($sede);
    }
}