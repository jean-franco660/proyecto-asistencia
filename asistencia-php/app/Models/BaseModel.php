<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Clase base con operaciones CRUD genéricas.
 * Cada modelo extiende esta clase y define:
 *   - protected string $table    → nombre de la tabla en BD
 *   - protected bool $softDelete → true si usa deleted_at
 */
abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $softDelete = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Busca un registro por su ID */
    public function find(int $id): array |false
    {
        $q = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        if ($this->softDelete)
            $q .= ' AND deleted_at IS NULL';

        $stmt = $this->db->prepare($q);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** Devuelve todos los registros */
    public function all(): array
    {
        $q = "SELECT * FROM {$this->table}";
        if ($this->softDelete)
            $q .= ' WHERE deleted_at IS NULL';
        return $this->db->query($q)->fetchAll();
    }

    /**
     * Inserta un registro y retorna el ID generado.
     * $data = ['columna' => 'valor', ...]
     */
    public function create(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $places = ':' . implode(', :', array_keys($data));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$cols}) VALUES ({$places})"
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    /** Actualiza un registro por ID */
    public function update(int $id, array $data): bool
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data[$this->primaryKey] = $id;

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey} = :{$this->primaryKey}"
        );
        return $stmt->execute($data);
    }

    /** Elimina un registro (físicamente o soft delete) */
    public function delete(int $id): bool
    {
        if ($this->softDelete) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute([':id' => $id]);
    }
}
