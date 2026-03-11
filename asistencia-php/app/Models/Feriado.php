<?php
namespace App\Models;

class Feriado extends BaseModel
{
    protected string $table = 'feriados';

    /** Devuelve todos los feriados activos (nacionales + de una sede) */
    public function activosParaSede(int $sedeId): array
    {
        return $this->query("
            SELECT * FROM `{$this->table}`
            WHERE activo = 1
              AND (tipo = 'nacional' OR sede_id = ?)
            ORDER BY mes, dia
        ", [$sedeId]);
    }

    /** Verifica si una fecha es feriado para la sede dada */
    public function esFeriado(string $fecha, int $sedeId): bool
    {
        $dia = (int) date('j', strtotime($fecha));
        $mes = (int) date('n', strtotime($fecha));

        $stmt = $this->db->prepare("
            SELECT id FROM `{$this->table}`
            WHERE activo = 1 AND dia = ? AND mes = ?
              AND (tipo = 'nacional' OR sede_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$dia, $mes, $sedeId]);
        return (bool) $stmt->fetch();
    }
}