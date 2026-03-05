<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioWebController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function soloSuperAdmin(): void
    {
        $rol = $_REQUEST['auth_user']['rol'] ?? '';
        if ($rol !== 'super_admin')
            Response::error('Solo el super_admin puede gestionar administradores', 403);
    }

    /** GET /v1/web/usuarios-web — listar admins y supervisores */
    public function index(Request $req): void
    {
        $this->soloSuperAdmin();
        $stmt = $this->db->query("
            SELECT id, nombre, email, rol, estado, created_at
            FROM usuarios_web
            ORDER BY rol, nombre
        ");
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/usuarios-web/{id} */
    public function show(Request $req): void
    {
        $this->soloSuperAdmin();
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado FROM usuarios_web WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Usuario web no encontrado');
        Response::success($user);
    }

    /** POST /v1/web/usuarios-web — crear admin o supervisor */
    public function store(Request $req): void
    {
        $this->soloSuperAdmin();

        $nombre   = (string) $req->input('nombre');
        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');
        $rol      = (string) $req->input('rol'); // administrador | supervisor

        $errors = [];
        if (!$nombre)   $errors[] = 'nombre es requerido';
        if (!$email)    $errors[] = 'email es requerido';
        if (!$password) $errors[] = 'password es requerido';
        if (!in_array($rol, ['administrador', 'supervisor'])) $errors[] = 'rol inválido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        $stmt = $this->db->prepare("SELECT id FROM usuarios_web WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) Response::error('El email ya está registrado', 422);

        $stmt = $this->db->prepare("
            INSERT INTO usuarios_web (nombre, email, password, rol, estado)
            VALUES (:n, :e, :p, :r, 'ACTIVO')
        ");
        $stmt->execute([
            ':n' => $nombre,
            ':e' => $email,
            ':p' => password_hash($password, PASSWORD_BCRYPT),
            ':r' => $rol,
        ]);

        Response::success(['id' => $this->db->lastInsertId()], 'Usuario web creado correctamente', 201);
    }

    /** PUT /v1/web/usuarios-web/{id} — actualizar nombre, email o rol */
    public function update(Request $req): void
    {
        $this->soloSuperAdmin();
        $id = (int) $req->param('id');

        $campos = [];
        $params = [];
        foreach (['nombre', 'email', 'rol'] as $campo) {
            if ($req->input($campo) !== null) {
                $campos[] = "`{$campo}` = ?";
                $params[] = $req->input($campo);
            }
        }
        if (empty($campos)) Response::unprocessable('No hay campos a actualizar');

        $params[] = $id;
        $this->db->prepare("UPDATE usuarios_web SET " . implode(', ', $campos) . " WHERE id = ?")->execute($params);
        Response::success(null, 'Usuario web actualizado correctamente');
    }

    /** PATCH /v1/web/usuarios-web/{id}/estado — activar/desactivar */
    public function cambiarEstado(Request $req): void
    {
        $this->soloSuperAdmin();
        $id     = (int) $req->param('id');
        $estado = (string) $req->input('estado');

        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido');

        $this->db->prepare("UPDATE usuarios_web SET estado = ? WHERE id = ?")->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }
}