<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioWeb;
use Firebase\JWT\JWT;

class AuthWebController
{
    private UsuarioWeb $model;

    public function __construct() {
        $this->model = new UsuarioWeb();
    }

    public function login(Request $req): void
    {
        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');

        if (!$email || !$password)
            Response::unprocessable('Email y contraseña son requeridos');

        $user = $this->model->findByEmail($email);

        if (!$user || !password_verify($password, $user['password']))
            Response::unauthorized('Credenciales incorrectas');

        if ($user['estado'] !== 'ACTIVO')
            Response::error('Cuenta deshabilitada. Contacte al administrador.', 403);

        $payload = [
            'sub'  => $user['id'],
            'rol'  => $user['rol'],
            'tipo' => 'web',   
            'iat'  => time(),
            'exp'  => time() + 86400,
        ];

        $token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        unset($user['password']);

        Response::success([
            'token'   => $token,
            'usuario' => $user,
        ], 'Inicio de sesión exitoso');
    }

    /** GET /v1/web/me */
    public function me(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $user   = $this->model->find($userId);
        if (!$user) Response::notFound('Usuario no encontrado');
        unset($user['password']);
        Response::success($user);
    }

    /** POST /v1/web/logout */
    public function logout(Request $req): void
    {
        Response::success(null, 'Sesión cerrada correctamente');
    }
}