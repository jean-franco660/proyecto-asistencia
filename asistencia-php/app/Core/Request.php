<?php
namespace App\Core;

/**
 * Encapsula los datos de entrada de una petición HTTP:
 * - Parámetros de URL (/ruta/{id})
 * - Body JSON o POST
 * - Cabeceras HTTP
 */
class Request
{
    private array $params;
    private array $body;

    public function __construct(array $params = [])
    {
        $this->params = $params;

        // Lee el body crudo y lo decodifica como JSON
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);

        // Si no es JSON válido, usa el $_POST (formularios HTML)
        $this->body = is_array($json) ? $json : $_POST;
    }

    /** Obtiene un parámetro de la URL: /usuarios/{id} → param('id') */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** Obtiene un campo del body JSON o POST */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Devuelve todos los campos del body */
    public function all(): array
    {
        return $this->body;
    }

    /** Obtiene una cabecera HTTP. Ej: header('Authorization') */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    /** Método HTTP de la petición (GET, POST, etc.) */
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}
