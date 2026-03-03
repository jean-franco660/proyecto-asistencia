<?php

namespace App\Middleware;

use App\Core\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class AuthAppMiddleware
{
    public static function handle(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $header, $m))
            Response::unauthorized('Token requerido');

        try {
            $decoded = JWT::decode($m[1], new Key($_ENV['JWT_SECRET'], 'HS256'));
            $payload = (array) $decoded;

            // Verificar que es token de app (no de web)
            if (($payload['tipo'] ?? '') !== 'app')
                Response::unauthorized('Token no válido para la app móvil');

            $_REQUEST['auth_user'] = $payload;

        } catch (ExpiredException $e) {
            Response::unauthorized('Token expirado');
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido');
        }
    }
}