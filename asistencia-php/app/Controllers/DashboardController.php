<?php
namespace App\Controllers;

use App\Core\JwtAuth;
use App\Core\Request;
use App\Core\Response;

class DashboardController
{
    /**
     * GET /api/dashboard
     * Header requerido: Authorization: Bearer <token>
     *
     * Respuesta exitosa (200):
     * {
     *   "message": "Bienvenido al dashboard",
     *   "user": { "id": 1, "nombre": "...", "email": "...", "rol": "..." }
     * }
     *
     * Respuesta de error (401): token ausente, inválido o expirado.
     */
    public function index(Request $req): void
    {
        // Valida el token JWT y extrae los datos del usuario
        $user = JwtAuth::fromRequest($req);

        Response::json([
            'message' => 'Bienvenido al dashboard',
            'user' => $user,
        ]);
    }
}
