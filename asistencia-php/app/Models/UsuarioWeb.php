<?php
namespace App\Models;

/**
 * Modelo para la tabla `usuarios_web`.
 * Maneja los usuarios con acceso al panel administrativo.
 */
class UsuarioWeb extends BaseModel
{
    protected string $table = 'usuarios_web';
    protected bool $softDelete = true;

    /**
     * Busca un usuario por su email.
     * Usado en el proceso de login.
     */
    public function findByEmail(string $email): array |false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE email = :email AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
}
