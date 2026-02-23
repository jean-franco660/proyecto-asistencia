<?php
namespace App\Core;

/**
 * Métodos estáticos para enviar respuestas HTTP:
 * - JSON (para APIs)
 * - redirect (para formularios web)
 */
class Response
{
    public static function json(mixed $data, int $code = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}
