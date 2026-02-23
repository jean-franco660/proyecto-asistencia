<?php
namespace App\Core;

/**
 * Router HTTP simple.
 * Soporta parámetros dinámicos en la URL: /usuarios/{id}
 * y los métodos GET, POST, PUT, DELETE.
 */
class Router
{
    private array $routes = [];

    private function addRoute(string $method, string $path, array $handler): void
    {
        // /usuarios/{id} → regex con grupo nombrado: (?P<id>[^/]+)
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => "#^{$pattern}$#",
            'handler' => $handler,
        ];
    }

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Limpia la URI: extrae solo el path (sin query string) y normaliza slashes
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $uri = '/' . trim($uri, '/');
        if ($uri === '')
            $uri = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                // Extrae solo los parámetros con clave de texto (los grupos nombrados)
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->$action(new Request($params));
                return;
            }
        }

        // 404 — ruta no encontrada
        http_response_code(404);
        echo '<h1 style="font-family:sans-serif">404 — Página no encontrada</h1>';
    }
}
