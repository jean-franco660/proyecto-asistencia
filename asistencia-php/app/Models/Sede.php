<?php

namespace App\Models;

class Sede extends BaseModel
{
    protected string $table = 'sedes';

    /** Devuelve solo las sedes activas */
    public function activas(): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE activa = 1 ORDER BY nombre");
    }

    /** Calcula la distancia en metros con la fórmula Haversine (en SQL) */
    public function cercanas(float $lat, float $lng, int $radio = 500): array
    {
        return $this->query("
            SELECT *, (6371000 * acos(
                cos(radians(?)) * cos(radians(latitud)) *
                cos(radians(longitud) - radians(?)) +
                sin(radians(?)) * sin(radians(latitud))
            )) AS distancia
            FROM sedes
            WHERE activa = 1
            HAVING distancia <= ?
            ORDER BY distancia
        ", [$lat, $lng, $lat, $radio]);
    }
}