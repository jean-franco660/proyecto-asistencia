<?php
namespace App\Models;

class AsistenciaDiaria extends BaseModel
{
    protected string $table = 'asistencias_diarias';

    public function porAsistencia(int $asistenciaId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE asistencia_id = ? ORDER BY marcada_en ASC",
            [$asistenciaId]
        );
    }

    /** Marcaciones OBSERVADAS pendientes de revisión de una sede */
    public function observadasPendientes(int $sedeId): array
    {
        return $this->query("
            SELECT ad.*, a.usuario_app_id, a.fecha
            FROM asistencias_diarias ad
            INNER JOIN asistencias a ON a.id = ad.asistencia_id
            WHERE a.sede_id = ?
              AND ad.estado_marcacion = 'OBSERVADA'
              AND ad.estado_revision  = 'PENDIENTE'
            ORDER BY ad.marcada_en DESC
        ", [$sedeId]);
    }
}