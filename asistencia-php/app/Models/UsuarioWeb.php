<?php

namespace App\Models;

class UsuarioWeb extends BaseModel
{
    protected string $table = 'usuarios_web';

    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy('email', $email);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE email = ?";
        $params = [$email];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}