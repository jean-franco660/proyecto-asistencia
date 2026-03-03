<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', __DIR__ . '/..');
require ROOT_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\JwtAuth;
use App\Models\UsuarioWeb;

$env = parse_ini_file(ROOT_PATH . '/.env');
foreach ($env as $k => $v)
    $_ENV[$k] = $v;

echo "=== Diagnóstico API Asistencia ===\n\n";

// 1. Conexión BD
echo "[1] Conexión a la base de datos... ";
try {
    $db = Database::getInstance();
    echo "OK\n";
}
catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Buscar usuario
echo "[2] Buscando usuario admin@asistencia.com... ";
$model = new UsuarioWeb();
$usuario = $model->findByEmail('admin@asistencia.com');
if (!$usuario) {
    echo "ERROR: No encontrado en la BD\n";
    exit(1);
}
echo "OK (nombre: {$usuario['nombre']})\n";

// 3. Verificar password
echo "[3] Verificando password 'Admin123'... ";
if (!password_verify('Admin123', $usuario['password'])) {
    echo "ERROR: password_verify() falló\n";
    echo "     Hash en BD: " . $usuario['password'] . "\n";
    exit(1);
}
echo "OK\n";

// 4. Generar JWT
echo "[4] Generando token JWT... ";
unset($usuario['password'], $usuario['deleted_at']);
$token = JwtAuth::encode($usuario);
echo "OK\n";
echo "    Token: " . substr($token, 0, 50) . "...\n";

echo "\n=== Todo OK. La API debería funcionar. ===\n";
