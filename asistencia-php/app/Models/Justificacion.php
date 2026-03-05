<?php
namespace App\Models;

class Justificacion extends BaseModel
{
    protected string $table = 'justificaciones';

    public function porUsuario(int $usuarioId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE usuario_app_id = ? ORDER BY created_at DESC",
            [$usuarioId]
        );
    }

    public function pendientesDeSede(int $sedeId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE sede_id = ? AND estado = 'PENDIENTE' ORDER BY created_at DESC",
            [$sedeId]
        );
    }
}