<?php
namespace App\Core;

class Response
{
    /**
     * Respuesta exitosa.
     * { "success": true, "message": "...", "data": ... }
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Error genérico con código HTTP personalizado.
     * { "success": false, "message": "..." }
     */
    public static function error(string $message, int $status = 400): void
    {
        self::json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * 401 Unauthorized — token inválido o credenciales incorrectas.
     */
    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 401);
    }

    /**
     * 403 Forbidden — autenticado pero sin permisos.
     */
    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 403);
    }

    /**
     * 404 Not Found.
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    /**
     * 422 Unprocessable Entity — validación fallida.
     * { "success": false, "message": "...", "errors": [...] }
     */
    public static function unprocessable(string $message = 'Datos inválidos', array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Método base: envía el JSON y termina la ejecución.
     * IMPORTANTE: exit() detiene el script — ningún código después
     * de llamar a Response::success() o Response::error() se ejecuta.
     */
    private static function json(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
}