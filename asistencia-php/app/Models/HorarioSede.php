<?php


namespace App\Models;

class HorarioSede extends BaseModel
{
    protected string $table = 'horarios_sede';

    public function porSede(int $sedeId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE sede_id = ? AND activo = 1 ORDER BY hora_entrada",
            [$sedeId]
        );
    }
}   