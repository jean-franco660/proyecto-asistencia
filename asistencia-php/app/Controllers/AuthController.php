<?php
namespace App\Controllers;

use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioWeb;

class AuthController
{
    /**
     * POST /api/auth/login
     * Body JSON: { "email": "...", "password": "..." }
     *
     * Respuesta exitosa (200):
     * {
     *   "token": "eyJ...",
     *   "expires_in": 3600,
     *   "user": { "id": 1, "nombre": "...", "email": "...", "rol": "..." }
     * }
     *
     * Respuesta de error (400 / 401):
     * { "error": "..." }
     */
    public function login(Request $req): void
    {
        $email = trim((string)$req->input('email', ''));
        $password = (string)$req->input('password', '');

        // Validación básica
        if (empty($email) || empty($password)) {
            Response::json(['error' => 'Los campos email y password son obligatorios'], 400);
        }

        $model = new UsuarioWeb();
        $usuario = $model->findByEmail($email);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            Response::json(['error' => 'Credenciales incorrectas'], 401);
        }

        // Excluir el hash de contraseña del payload del token y la respuesta
        unset($usuario['password'], $usuario['deleted_at']);

        $token = JwtAuth::encode($usuario);

        Response::json([
            'token' => $token,
            'expires_in' => (int)($_ENV['JWT_EXPIRY'] ?? 3600),
            'user' => $usuario,
        ]);
    }
}
