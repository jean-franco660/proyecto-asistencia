<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioApp;
use Firebase\JWT\JWT;

/**
 * AuthAppController - Login y perfil para la app móvil
 */
class AuthAppController
{
    private Response $response;
    private UsuarioApp $model;

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new UsuarioApp();
    }

    /**
     * POST /v1/app/login
     */
    public function login(Request $request): void
    {
        $codigo   = trim($request->input('codigo_empleado', ''));
        $password = $request->input('password', '');

        if (!$codigo || !$password) {
            $this->response->validationError([
                'codigo_empleado' => 'Requerido',
                'password'        => 'Requerido',
            ]);
        }

        $usuario = $this->model->findByCodigo($codigo);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $this->response->unauthorized('Credenciales incorrectas.');
        }

        $token = $this->generateToken($usuario, 'app');

        $this->response->success([
            'token'   => $token,
            'usuario' => $this->sanitize($usuario),
        ], 'Login exitoso.');
    }

    /**
     * GET /v1/app/perfil  [protegida]
     */
    public function perfil(Request $request): void
    {
        $userId  = $request->getAttribute('auth_user_id');
        $usuario = $this->model->find((int)$userId);

        if (!$usuario) {
            $this->response->notFound('Usuario no encontrado.');
        }

        $this->response->success($this->sanitize($usuario));
    }

    /**
     * POST /v1/app/logout  [protegida]
     * En JWT stateless simplemente confirmamos el cierre.
     */
    public function logout(Request $request): void
    {
        $this->response->success(null, 'Sesión cerrada.');
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────

    private function generateToken(array $usuario, string $type): string
    {
        $secret     = $_ENV['JWT_SECRET'] ?? 'secret';
        $expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 3600);

        $payload = [
            'iss'  => 'asistencia-api',
            'iat'  => time(),
            'exp'  => time() + $expiration,
            'sub'  => $usuario['id'],
            'rol'  => $usuario['rol'],
            'type' => $type,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function sanitize(array $usuario): array
    {
        unset($usuario['password']);
        return $usuario;
    }
}
