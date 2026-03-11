<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\HorarioSede;
use App\Core\Database;

class HorarioWebController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * GET /v1/web/horarios?sede_id=2
     * Supervisor solo ve horarios de sus sedes.
     */
    public function index(Request $req): void
    {
        $sedeId = (int) $req->query('sede_id', 0);
        $rol    = $_REQUEST['auth_user']['rol'] ?? '';
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $sql    = 'SELECT * FROM horarios_sede WHERE 1=1';
        $params = [];

        if ($sedeId) {
            $sql .= ' AND sede_id = :sid';
            $params[':sid'] = $sedeId;
        } elseif ($rol === 'supervisor') {
            $sql .= ' AND sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )';
            $params[':uid'] = $userId;
        }

        $sql .= ' ORDER BY hora_entrada';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/web/horarios
     * Al crear, asigna automáticamente el horario a trabajadores de esa sede que aún no tienen horario asignado.
     */
    public function store(Request $req): void
    {
        $data = $req->only([
            'sede_id','nombre_turno','hora_entrada','hora_salida',
            'tolerancia_entrada_minutos','tolerancia_salida_minutos','dias_semana'
        ]);

        $errors = [];
        if (empty($data['sede_id']))         $errors[] = 'sede_id es requerido';
        if (empty($data['nombre_turno']))    $errors[] = 'nombre_turno es requerido';
        if (empty($data['hora_entrada']))    $errors[] = 'hora_entrada es requerido';
        if (empty($data['hora_salida']))     $errors[] = 'hora_salida es requerido';
        if (empty($data['dias_semana']))     $errors[] = 'dias_semana es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        $data['dias_semana'] = json_encode($data['dias_semana']);
        $data['activo']      = 1;
        $data['tolerancia_entrada_minutos'] = (int)($data['tolerancia_entrada_minutos'] ?? 0);
        $data['tolerancia_salida_minutos']  = (int)($data['tolerancia_salida_minutos'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO horarios_sede
                (sede_id, nombre_turno, hora_entrada, hora_salida,
                 tolerancia_entrada_minutos, tolerancia_salida_minutos, dias_semana, activo)
            VALUES (:sid, :nt, :he, :hs, :te, :ts, :ds, 1)
        ");
        $stmt->execute([
            ':sid' => $data['sede_id'],
            ':nt'  => $data['nombre_turno'],
            ':he'  => $data['hora_entrada'],
            ':hs'  => $data['hora_salida'],
            ':te'  => $data['tolerancia_entrada_minutos'],
            ':ts'  => $data['tolerancia_salida_minutos'],
            ':ds'  => $data['dias_semana'],
        ]);
        $horarioId = (int) $this->db->lastInsertId();

        // Auto-asignar a trabajadores sin horario en esa sede
        $this->autoAsignarHorario((int)$data['sede_id'], $horarioId);

        $stmt = $this->db->prepare("SELECT * FROM horarios_sede WHERE id = :id");
        $stmt->execute([':id' => $horarioId]);
        Response::success($stmt->fetch(), 'Horario creado correctamente', 201);
    }

    /**
     * Auto-asigna el horario a trabajadores sin horario en esa sede.
     * Misma lógica que el HorariosInstitucionController de Laravel.
     */
    private function autoAsignarHorario(int $sedeId, int $horarioId): void
    {
        $stmt = $this->db->prepare("
            UPDATE usuario_app_sede
            SET horario_sede_id = :hid
            WHERE sede_id = :sid
              AND estado = 'ACTIVO'
              AND horario_sede_id IS NULL
        ");
        $stmt->execute([':hid' => $horarioId, ':sid' => $sedeId]);
    }

    public function update(Request $req): void
    {
        $id  = (int) $req->param('id');
        $data = $req->only([
            'nombre_turno','hora_entrada','hora_salida',
            'tolerancia_entrada_minutos','tolerancia_salida_minutos',
            'dias_semana','activo'
        ]);

        if (isset($data['dias_semana']) && is_array($data['dias_semana']))
            $data['dias_semana'] = json_encode($data['dias_semana']);

        // FIX Bug #9: los nombres de columna no tenían backticks en el SET dinámico.
        // Sin backticks, columnas con nombres reservados en MySQL fallarían.
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->db->prepare("UPDATE horarios_sede SET $sets WHERE id = :id");
        $stmt->execute($data);

        $stmt = $this->db->prepare("SELECT * FROM horarios_sede WHERE id = :id");
        $stmt->execute([':id' => $id]);
        Response::success($stmt->fetch(), 'Horario actualizado correctamente');
    }

    /** DELETE /v1/web/horarios/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("DELETE FROM horarios_sede WHERE id = :id");
        $stmt->execute([':id' => $id]);
        Response::success(null, 'Horario eliminado correctamente');
    }
}