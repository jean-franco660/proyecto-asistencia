<?php
namespace App\Core;

class Request
{
    private array $body;      // datos del body (JSON o form)
    private array $query;     // ?param=valor de la URL
    private array $params;    // {id} de la ruta (asignados por el Router)

    public function __construct()
    {
        // Intentar parsear body como JSON
        $json = json_decode(file_get_contents('php://input'), true);
        $this->body  = is_array($json) ? $json : $_POST;
        $this->query  = $_GET;
        $this->params = [];
    }

    /**
     * Leer del body (POST/JSON).
     * Ejemplo: $req->input('email')
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Leer query string (?key=valor).
     * Ejemplo: $req->query('page', 1)
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Leer parámetro de ruta ({id}, {usuarioId}, etc.)
     * Ejemplo: $req->param('id')
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Obtener solo los campos indicados del body.
     * Ejemplo: $req->only(['nombre', 'email'])
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    /**
     * Usado por el Router para inyectar parámetros de ruta.
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}