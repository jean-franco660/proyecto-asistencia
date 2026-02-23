<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton de conexión PDO.
 * Una sola instancia de conexión durante toda la petición HTTP.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_DATABASE'] ?? 'asistencia_db'
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USERNAME'] ?? 'root',
                    $_ENV['DB_PASSWORD'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
                    );
            }
            catch (PDOException $e) {
                // En desarrollo muestra el error; en producción muestra un mensaje genérico
                $message = ($_ENV['APP_DEBUG'] ?? false)
                    ? 'DB Error: ' . $e->getMessage()
                    : 'Error de conexión a la base de datos';

                http_response_code(500);
                die('<h1 style="font-family:sans-serif;color:#c00">⚠ ' . htmlspecialchars($message) . '</h1>');
            }
        }

        return self::$instance;
    }

    private function __construct()
    {
    }
    private function __clone()
    {
    }
}
