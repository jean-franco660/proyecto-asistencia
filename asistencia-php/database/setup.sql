-- ============================================================
-- Script SQL: Sistema de Asistencia — Tabla usuarios_web
-- Ejecutar en phpMyAdmin o MySQL CLI
-- ============================================================

-- 1. Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS asistencia_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE asistencia_db;

-- 2. Tabla usuarios_web
CREATE TABLE IF NOT EXISTS usuarios_web (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(150) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    rol        ENUM('super_admin','administrador','supervisor') DEFAULT 'administrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- 3. Insertar usuario administrador de prueba
--    Contraseña: Admin123
--    Hash generado con PHP: password_hash('Admin123', PASSWORD_DEFAULT)
INSERT INTO usuarios_web (nombre, email, password, rol)
VALUES (
    'Administrador',
    'admin@asistencia.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'super_admin'
)
ON DUPLICATE KEY UPDATE nombre = nombre; -- No inserta si ya existe

-- ============================================================
-- CREDENCIALES DE PRUEBA:
--   Email:    admin@asistencia.com
--   Password: Admin123
-- ============================================================

-- Si quieres cambiar la contraseña, ejecuta en PHP:
-- echo password_hash('TuNuevaContraseña', PASSWORD_DEFAULT);
-- Y reemplaza el hash en la columna password.
