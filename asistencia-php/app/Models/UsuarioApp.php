<?php
namespace App\Models;

class UsuarioApp extends BaseModel
{
    protected string $table = 'usuarios_app';

    public function findByCodigo(string $codigo): ?array
    {
        return $this->findOneBy('codigo_empleado', $codigo);
    }

    /** Devuelve el trabajador con su asignación activa (sede + horario) */
    public function findConAsignacion(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, uas.sede_id, uas.horario_sede_id, uas.cargo AS cargo_asignado,
                   s.nombre AS sede_nombre, hs.nombre_turno,
                   hs.hora_entrada, hs.hora_salida
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s             ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs    ON hs.id = uas.horario_sede_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function codigoExists(string $codigo, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE codigo_empleado = ?";
        $params = [$codigo];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}