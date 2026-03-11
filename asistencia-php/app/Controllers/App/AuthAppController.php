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

        // FIX Bug #2: validationError() no existía en Response. Se reemplaza
        // por Response::unprocessable() que sí existe y acepta array de errores.
        if (!$codigo || !$password) {
            Response::unprocessable('Datos requeridos', [
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
        // FIX Bug #3: Request::getAttribute() no existe. El payload JWT
        // lo inyecta el Middleware en $_REQUEST['auth_user']['sub'].
        $userId  = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $usuario = $this->model->find($userId);

        if (!$usuario) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($this->sanitize($usuario));
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
            // FIX Bug #1: el claim era 'type' pero AuthAppMiddleware verifica 'tipo'.
            // Con 'type' el middleware rechazaba TODOS los tokens de la app.
            'tipo' => $type,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function sanitize(array $usuario): array
    {
        unset($usuario['password']);
        return $usuario;
    }
}
