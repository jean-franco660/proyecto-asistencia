<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    // Registro de rutas públicas
    public function get(string $path, array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function patch(string $path, array $handler): void  { $this->add('PATCH',  $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    // Rutas protegidas JWT tipo 'app' (trabajadores)
    public function authAppGet(string $p, array $h): void    { $this->add('GET',    $p, $h, 'app'); }
    public function authAppPost(string $p, array $h): void   { $this->add('POST',   $p, $h, 'app'); }
    public function authAppPut(string $p, array $h): void    { $this->add('PUT',    $p, $h, 'app'); }
    public function authAppPatch(string $p, array $h): void  { $this->add('PATCH',  $p, $h, 'app'); }
    public function authAppDelete(string $p, array $h): void { $this->add('DELETE', $p, $h, 'app'); }

    //  Rutas protegidas JWT tipo 'web' (administradores)
    public function authWebGet(string $p, array $h): void    { $this->add('GET',    $p, $h, 'web'); }
    public function authWebPost(string $p, array $h): void   { $this->add('POST',   $p, $h, 'web'); }
    public function authWebPut(string $p, array $h): void    { $this->add('PUT',    $p, $h, 'web'); }
    public function authWebPatch(string $p, array $h): void  { $this->add('PATCH',  $p, $h, 'web'); }
    public function authWebDelete(string $p, array $h): void { $this->add('DELETE', $p, $h, 'web'); }

    private function add(string $method, string $path, array $handler, ?string $auth = null): void
    {
        // Convertir {param} en grupo de captura de regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'auth'    => $auth,   // null = pública, 'app' o 'web' = protegida
            'params'  => [],
        ];
    }

    /**
     * FIX Bug #6: Se añadió el parámetro Request $request para evitar que
     * dispatch() creara un segundo objeto Request ignorando el de index.php.
     */
    public function dispatch(Request $request): void
    {
        // Manejar preflight CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            http_response_code(204);
            exit();
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $req    = $request; // Usa el Request inyectado desde index.php

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Inyectar parámetros de ruta en el Request
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $req->setParams($params);

                // Ejecutar middleware si la ruta es protegida
                if ($route['auth'] === 'app') {
                    \App\Middleware\AuthAppMiddleware::handle();
                } elseif ($route['auth'] === 'web') {
                    \App\Middleware\AuthWebMiddleware::handle();
                }

                // Instanciar controlador y llamar al método
                [$class, $method] = $route['handler'];
                $controller = new $class();
                $controller->$method($req);
                return;
            }
        }

        Response::notFound('Ruta no encontrada');
    }
}