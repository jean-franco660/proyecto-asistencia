<?php
namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance(); // PDO directo
    }

    public function all(string $orderBy = ''): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy) {
            // Validar que solo contenga nombres de columna válidos
            // Patrón: "columna ASC/DESC, columna2 ASC/DESC"
            $parts = array_map('trim', explode(',', $orderBy));
            
            foreach ($parts as $part) {
                // Parsear "columna ASC/DESC"
                if (!preg_match('/^(\w+)(?:\s+(ASC|DESC))?$/i', $part, $m)) {
                    throw new InvalidArgumentException("Invalid ORDER BY clause: {$part}");
                }
            }
            // Usar comillas para nombres de columnas
            $sql .= " ORDER BY " . preg_replace('/(\w+)\s*(ASC|DESC)?/i', '`$1` $2', $orderBy);
        }
        return $this->db->query($sql)->fetchAll();
    }
    public function find(int|string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findOneBy(string $column, mixed $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int|string
    {
        $columns      = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->db->prepare("INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }

    public function update(int|string $id, array $data): bool
    {
        $sets = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $stmt = $this->db->prepare("UPDATE `{$this->table}` SET {$sets} WHERE `{$this->primaryKey}` = ?");
        $values   = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }

    public function delete(int|string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([$id]);
    }

    // Helpers internos
    protected function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $bindings = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Expone la instancia PDO para que los controllers puedan ejecutar
     * queries custom a través del modelo (ej: SedeWebController::index).
     * FIX Lint: SedeWebController llamaba $this->model->db() que no existía.
     */
    public function db(): \PDO
    {
        return $this->db;
    }
}