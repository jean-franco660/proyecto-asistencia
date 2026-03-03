<?php
namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Helper para generar y validar tokens JWT.
 * Algoritmo: HS256 (HMAC-SHA256)
 * Secret:    JWT_SECRET del archivo .env
 * Expiración: JWT_EXPIRY segundos (default 3600 = 1 hora)
 */
class JwtAuth
{
    private static string $algo = 'HS256';

    private static function secret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            // Si no hay secret definido, la API no puede operar de forma segura
            Response::json(['error' => 'JWT_SECRET no configurado en .env'], 500);
        }
        return $secret;
    }

    /**
     * Genera un token JWT con los datos del usuario.
     * $payload = array asociativo con datos a incluir (no incluir password)
     */
    public static function encode(array $payload): string
    {
        $now = time();
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 3600);

        $claims = [
            'iat' => $now, // issued at
            'exp' => $now + $expiry, // expiration
            'data' => $payload,
        ];

        return JWT::encode($claims, self::secret(), self::$algo);
    }

    /**
     * Decodifica y valida un token JWT.
     * Retorna el payload 'data' si el token es válido.
     * Llama a Response::json con 401 si el token es inválido o expiró.
     */
    public static function decode(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), self::$algo));
            return (array)$decoded->data;
        }
        catch (ExpiredException) {
            Response::json(['error' => 'Token expirado'], 401);
        }
        catch (SignatureInvalidException) {
            Response::json(['error' => 'Token con firma inválida'], 401);
        }
        catch (\Exception) {
            Response::json(['error' => 'Token inválido'], 401);
        }
    }

    /**
     * Extrae y valida el token del header Authorization: Bearer <token>.
     * Llama a Response::json con 401 si el header no está presente.
     */
    public static function fromRequest(Request $req): array
    {
        $header = $req->header('Authorization') ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            Response::json(['error' => 'Token requerido. Usa Authorization: Bearer <token>'], 401);
        }

        $token = trim(substr($header, 7));
        return self::decode($token);
    }
}
