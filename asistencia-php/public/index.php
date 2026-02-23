<?php
declare(strict_types = 1)
;

define('ROOT_PATH', dirname(__DIR__));

// Cargar Composer autoload
require ROOT_PATH . '/vendor/autoload.php';

// Cargar .env
if (file_exists(ROOT_PATH . '/.env')) {
    $env = parse_ini_file(ROOT_PATH . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

use App\Core\Session;
use App\Core\Router;

// Iniciar sesión antes de cualquier output
Session::start();

// Crear el router y cargar rutas web
$router = new Router();
require ROOT_PATH . '/routes/web.php';
$router->dispatch();
