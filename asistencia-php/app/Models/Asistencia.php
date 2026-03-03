<?php


namespace App\Models;

class Asistencia extends BaseModel
{
    protected string $table = 'asistencias';

    public function delDia(int $usuarioId, int $sedeId, string $fecha): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE usuario_app_id = ? AND sede_id = ? AND fecha = ?
            LIMIT 1
        ");
        $stmt->execute([$usuarioId, $sedeId, $fecha]);
        return $stmt->fetch() ?: null;
    }
}