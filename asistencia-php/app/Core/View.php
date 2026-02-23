<?php
namespace App\Core;

/**
 * Renderiza archivos PHP como vistas.
 * Convierte el nombre punteado a ruta de archivo:
 *   'auth.login'       → app/Views/auth/login.php
 *   'dashboard.index'  → app/Views/dashboard/index.php
 */
class View
{
    public static function render(string $view, array $data = []): never
    {
        // Expone cada clave del array $data como variable local
        // Ej: ['user' => $obj] → $user disponible en la vista
        extract($data, EXTR_SKIP);

        $file = ROOT_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            http_response_code(500);
            die("<h1>Vista no encontrada: <code>{$view}</code></h1>");
        }

        require $file;
        exit;
    }
}
