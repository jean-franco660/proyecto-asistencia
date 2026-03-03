-- ============================================================
-- SISTEMA DE ASISTENCIA — Base de datos (terminología empresarial)
-- Ejecutar: mysql -u root -p < database/setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS asistencia_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE asistencia_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. ADMINISTRADORES WEB (panel de gestión)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios_web (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password        VARCHAR(255)    NOT NULL,
    rol             ENUM('super_admin','administrador','supervisor') NOT NULL DEFAULT 'supervisor',
    estado          ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_login    DATETIME        NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    INDEX idx_email  (email),
    INDEX idx_rol    (rol),
    INDEX idx_estado (estado)
);

-- ============================================================
-- 2. SEDES (sucursales / locales de la empresa)
-- ============================================================
CREATE TABLE IF NOT EXISTS sedes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_sede         VARCHAR(50)     NOT NULL UNIQUE,
    nombre              VARCHAR(255)    NOT NULL,
    rubro               VARCHAR(100)    NULL,
    distrito            VARCHAR(100)    NULL,
    provincia           VARCHAR(100)    NULL,
    region              VARCHAR(100)    NULL,
    direccion           VARCHAR(500)    NULL,
    latitud             DECIMAL(10,8)   NOT NULL,
    longitud            DECIMAL(11,8)   NOT NULL,
    radio               INT             NOT NULL DEFAULT 100,
    logo                VARCHAR(500)    NULL,
    activa              TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP NULL,
    INDEX idx_codigo (codigo_sede),
    INDEX idx_distrito (distrito)
);

-- Relación supervisor ↔ sedes
CREATE TABLE IF NOT EXISTS usuario_web_sede (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_web_id  INT UNSIGNED NOT NULL,
    sede_id         INT UNSIGNED NOT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_inicio    DATE         NULL,
    fecha_fin       DATE         NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_web_sede (usuario_web_id, sede_id),
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id)        REFERENCES sedes(id)        ON DELETE CASCADE
);

-- ============================================================
-- 3. HORARIOS DE SEDE (turnos)
-- ============================================================
CREATE TABLE IF NOT EXISTS horarios_sede (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sede_id                     INT UNSIGNED    NOT NULL,
    nombre_turno                VARCHAR(50)     NOT NULL,
    hora_entrada                TIME            NOT NULL,
    hora_salida                 TIME            NOT NULL,
    tolerancia_entrada_minutos  INT             NOT NULL DEFAULT 0,
    tolerancia_salida_minutos   INT             NOT NULL DEFAULT 0,
    dias_semana                 JSON            NOT NULL,   -- ["L","M","X","J","V"]
    activo                      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sede (sede_id),
    INDEX idx_activo (activo),
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE
);

-- ============================================================
-- 4. TRABAJADORES (usuarios de la app móvil)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios_app (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_empleado     VARCHAR(50)     NOT NULL UNIQUE,
    apellido_paterno    VARCHAR(100)    NOT NULL,
    apellido_materno    VARCHAR(100)    NOT NULL,
    nombres             VARCHAR(150)    NOT NULL,
    sexo                ENUM('M','F')   NOT NULL DEFAULT 'M',
    dni                 VARCHAR(20)     NULL UNIQUE,
    fecha_nacimiento    DATE            NULL,
    telefono            VARCHAR(20)     NULL,
    password            VARCHAR(255)    NOT NULL,
    cargo               VARCHAR(100)    NULL,
    acceso_habilitado   TINYINT(1)      NOT NULL DEFAULT 1,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_login        DATETIME        NULL,
    foto                VARCHAR(500)    NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP NULL,
    INDEX idx_codigo  (codigo_empleado),
    INDEX idx_estado  (estado),
    INDEX idx_acceso  (acceso_habilitado)
);

-- Relación trabajador ↔ sede ↔ horario
CREATE TABLE IF NOT EXISTS usuario_app_sede (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NULL,
    cargo               VARCHAR(100)    NULL,
    estado              ENUM('PENDIENTE','ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    fecha_inicio        DATE            NULL,
    fecha_fin           DATE            NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trabajador_sede_horario (usuario_app_id, sede_id, horario_sede_id),
    INDEX idx_estado (estado),
    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id)   ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)           ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id)   ON DELETE SET NULL
);

-- Log de cambios de horario desde la app (límite 1 por mes)
CREATE TABLE IF NOT EXISTS horario_cambio_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id  INT UNSIGNED    NOT NULL,
    sede_id         INT UNSIGNED    NOT NULL,
    horario_anterior JSON           NULL,
    horario_nuevo   JSON            NOT NULL,
    origen          ENUM('APP','WEB') NOT NULL DEFAULT 'APP',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_sede (usuario_app_id, sede_id),
    INDEX idx_origen (origen),
    FOREIGN KEY (usuario_app_id) REFERENCES usuarios_app(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id)        REFERENCES sedes(id)         ON DELETE CASCADE
);

-- ============================================================
-- 5. ASISTENCIAS (cabecera diaria por trabajador)
-- ============================================================
CREATE TABLE IF NOT EXISTS asistencias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NULL,
    fecha               DATE            NOT NULL,
    hora_entrada        TIME            NULL,
    hora_salida         TIME            NULL,
    minutos_tarde       INT             NULL DEFAULT 0,
    estado_diario       ENUM('FALTA','PRESENTE','TARDANZA','JUSTIFICADO','PENDIENTE')
                        NOT NULL DEFAULT 'FALTA',
    observacion         TEXT            NULL,
    revisado_por        INT UNSIGNED    NULL,   -- usuario_web que revisó
    revisado_en         DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_asistencia_dia (usuario_app_id, sede_id, fecha, horario_sede_id),
    INDEX idx_fecha        (fecha),
    INDEX idx_estado       (estado_diario),
    INDEX idx_usuario_fecha (usuario_app_id, fecha),
    INDEX idx_sede_fecha   (sede_id, fecha),
    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id)  ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)          ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id)  ON DELETE SET NULL,
    FOREIGN KEY (revisado_por)    REFERENCES usuarios_web(id)   ON DELETE SET NULL
);

-- ============================================================
-- 6. MARCACIONES INDIVIDUALES (ENTRADA / SALIDA con GPS)
-- ============================================================
CREATE TABLE IF NOT EXISTS asistencias_diarias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id       INT UNSIGNED    NOT NULL,
    tipo                ENUM('ENTRADA','SALIDA') NOT NULL,
    marcada_en          DATETIME        NOT NULL,
    latitud             DECIMAL(10,8)   NOT NULL,
    longitud            DECIMAL(11,8)   NOT NULL,
    dentro_rango        TINYINT(1)      NOT NULL DEFAULT 1,   -- siempre 1: la API rechaza (403) si está fuera de rango
    distancia_metros    INT             NULL,                 -- distancia real a la sede (informativa)
    estado_marcacion    ENUM('VALIDA','OBSERVADA') NOT NULL DEFAULT 'VALIDA',
    -- VALIDA:    marcó dentro del horario permitido (con tolerancia de entrada/salida)
    -- OBSERVADA: marcó ANTES de la hora de entrada, o fuera de la ventana de salida
    -- NOTA: fuera del rango GPS → API retorna 403, NO se guarda ningún registro
    motivo_observacion  VARCHAR(255)    NULL,                 -- 'FUERA_DE_HORARIO' | 'SIN_HORARIO_ASIGNADO'
    estado_revision     ENUM('PENDIENTE','APROBADA','MANTENER_OBSERVADA') NOT NULL DEFAULT 'APROBADA',
    offline_uuid        VARCHAR(100)    NULL UNIQUE,
    registrado_en       ENUM('APP_ONLINE','APP_OFFLINE') NOT NULL DEFAULT 'APP_ONLINE',
    foto                VARCHAR(500)    NULL,
    revisado_por        INT UNSIGNED    NULL,
    revisado_en         DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_asistencia   (asistencia_id),
    INDEX idx_tipo         (tipo),
    INDEX idx_estado_rev   (estado_revision),
    INDEX idx_offline_uuid (offline_uuid),
    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE,
    FOREIGN KEY (revisado_por)  REFERENCES usuarios_web(id) ON DELETE SET NULL
);

-- ============================================================
-- 7. JUSTIFICACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS justificaciones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NULL,
    asistencia_id       INT UNSIGNED    NULL,
    tipo                ENUM(
                            'ENFERMEDAD','PERMISO_PERSONAL','LICENCIA',
                            'COMISION_SERVICIO','CAPACITACION','DUELO',
                            'MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO'
                        ) NOT NULL,
    fecha_inicio        DATE            NOT NULL,
    fecha_fin           DATE            NOT NULL,
    dias                INT             GENERATED ALWAYS AS (DATEDIFF(fecha_fin, fecha_inicio) + 1) STORED,
    motivo              TEXT            NOT NULL,
    archivo_adjunto     VARCHAR(500)    NULL,
    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    usuario_web_id      INT UNSIGNED    NULL,
    observaciones       TEXT            NULL,
    fecha_revision      DATETIME        NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario  (usuario_app_id),
    INDEX idx_sede     (sede_id),
    INDEX idx_estado   (estado),
    INDEX idx_fechas   (fecha_inicio, fecha_fin),
    FOREIGN KEY (usuario_app_id) REFERENCES usuarios_app(id)   ON DELETE CASCADE,
    FOREIGN KEY (sede_id)        REFERENCES sedes(id)           ON DELETE CASCADE,
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id)    ON DELETE SET NULL,
    FOREIGN KEY (asistencia_id)  REFERENCES asistencias(id)     ON DELETE SET NULL,
    FOREIGN KEY (horario_sede_id)REFERENCES horarios_sede(id)   ON DELETE SET NULL
);

-- ============================================================
-- 8. FERIADOS
-- ============================================================
CREATE TABLE IF NOT EXISTS feriados (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha       DATE            NOT NULL UNIQUE,
    nombre      VARCHAR(200)    NOT NULL,
    tipo        ENUM('NACIONAL','LOCAL','EMPRESA') NOT NULL DEFAULT 'NACIONAL',
    sede_id     INT UNSIGNED    NULL,   -- NULL = aplica a todas las sedes
    activo      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fecha  (fecha),
    INDEX idx_activo (activo),
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL
);

-- ============================================================
-- 9. AUDITORÍA (registro de cambios importantes)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED    NULL,
    tipo_usuario    ENUM('APP','WEB') NOT NULL,
    accion          VARCHAR(100)    NOT NULL,   -- 'login', 'aprobar_justificacion', etc.
    modelo          VARCHAR(100)    NULL,       -- 'Justificacion', 'Asistencia', etc.
    modelo_id       INT UNSIGNED    NULL,
    datos_antes     JSON            NULL,
    datos_despues   JSON            NULL,
    ip              VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario    (usuario_id, tipo_usuario),
    INDEX idx_modelo     (modelo, modelo_id),
    INDEX idx_accion     (accion),
    INDEX idx_created_at (created_at)
);

-- ============================================================
-- 10. LOG DE IMPORTACIONES (carga masiva de trabajadores)
-- ============================================================
CREATE TABLE IF NOT EXISTS importacion_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_web_id  INT UNSIGNED    NOT NULL,
    tipo            ENUM('TRABAJADORES','SEDES','ASISTENCIAS') NOT NULL,
    archivo         VARCHAR(500)    NULL,
    total_filas     INT             NOT NULL DEFAULT 0,
    exitosas        INT             NOT NULL DEFAULT 0,
    fallidas        INT             NOT NULL DEFAULT 0,
    errores         JSON            NULL,
    estado          ENUM('PROCESANDO','COMPLETADO','ERROR') NOT NULL DEFAULT 'PROCESANDO',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_web_id) REFERENCES usuarios_web(id) ON DELETE CASCADE
);

-- ============================================================
-- 11. RESET DE CONTRASEÑAS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(150)    NOT NULL,
    token           VARCHAR(255)    NOT NULL UNIQUE,
    tipo_usuario    ENUM('APP','WEB') NOT NULL DEFAULT 'WEB',
    expires_at      DATETIME        NOT NULL,
    used_at         DATETIME        NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATOS DE PRUEBA (seed)
-- Password de todos: "password"
-- Hash bcrypt de "password": $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

-- Administradores web
INSERT INTO usuarios_web (nombre, email, password, rol) VALUES
('Super Admin',      'superadmin@empresa.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('Administrador',    'admin@empresa.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),
('Supervisor Lima',  'supervisor@empresa.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor');

-- Sedes
INSERT INTO sedes (codigo_sede, nombre, rubro, distrito, provincia, region, direccion, latitud, longitud, radio) VALUES
('SEDE-001', 'Sede Central Lima',    'Tecnología', 'Miraflores', 'Lima', 'Lima', 'Av. Larco 123',         -12.1219, -77.0282, 100),
('SEDE-002', 'Sucursal San Isidro',  'Tecnología', 'San Isidro',  'Lima', 'Lima', 'Calle Las Flores 45',  -12.0978, -77.0353, 150),
('SEDE-003', 'Sucursal Surco',       'Tecnología', 'Surco',       'Lima', 'Lima', 'Av. Primavera 890',    -12.1367, -76.9924, 100);

-- Horarios de sedes
INSERT INTO horarios_sede (sede_id, nombre_turno, hora_entrada, hora_salida, tolerancia_entrada_minutos, tolerancia_salida_minutos, dias_semana) VALUES
(1, 'Turno Mañana',   '08:00', '13:00', 10, 10, '["L","M","X","J","V"]'),
(1, 'Turno Tarde',    '13:00', '18:00', 10, 10, '["L","M","X","J","V"]'),
(1, 'Turno Noche',    '18:00', '23:00', 15, 15, '["L","M","X","J","V"]'),
(2, 'Turno Completo', '08:00', '17:00', 10, 10, '["L","M","X","J","V"]'),
(3, 'Turno Mañana',   '07:00', '14:00', 10, 10, '["L","M","X","J","V","S"]');

-- Trabajadores
INSERT INTO usuarios_app (codigo_empleado, apellido_paterno, apellido_materno, nombres, sexo, dni, password, cargo) VALUES
('EMP-001', 'García',    'López',   'Carlos Alberto', 'M', '12345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Desarrollador'),
('EMP-002', 'Martínez',  'Rojas',   'Ana Sofía',      'F', '23456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Diseñadora'),
('EMP-003', 'Quispe',    'Flores',  'Pedro José',     'M', '34567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'QA Tester'),
('EMP-004', 'Torres',    'Vega',    'Lucía Carmen',   'F', '45678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Project Manager'),
('EMP-005', 'Mendoza',   'Chávez',  'Roberto Luis',   'M', '56789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Analista'),
('EMP-006', 'Sánchez',   'Paredes', 'María Elena',    'F', '67890123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RRHH');

-- Asignaciones trabajador → sede → horario
INSERT INTO usuario_app_sede (usuario_app_id, sede_id, horario_sede_id, cargo, estado, fecha_inicio) VALUES
(1, 1, 1, 'Desarrollador',   'ACTIVO', '2025-01-01'),  -- Carlos  → Sede Central, Turno Mañana
(2, 1, 2, 'Diseñadora',      'ACTIVO', '2025-01-01'),  -- Ana     → Sede Central, Turno Tarde
(3, 2, 4, 'QA Tester',       'ACTIVO', '2025-01-01'),  -- Pedro   → San Isidro,   Turno Completo
(4, 1, 1, 'Project Manager', 'ACTIVO', '2025-01-01'),  -- Lucía   → Sede Central, Turno Mañana
(5, 3, 5, 'Analista',        'ACTIVO', '2025-01-01'),  -- Roberto → Surco,        Turno Mañana
(6, 1, 2, 'RRHH',            'ACTIVO', '2025-01-01');  -- María   → Sede Central, Turno Tarde

-- Supervisor → sus sedes
INSERT INTO usuario_web_sede (usuario_web_id, sede_id, activo, fecha_inicio) VALUES
(3, 1, 1, '2025-01-01'),
(3, 2, 1, '2025-01-01');

-- Feriados nacionales 2025
INSERT INTO feriados (fecha, nombre, tipo) VALUES
('2025-01-01', 'Año Nuevo',                        'NACIONAL'),
('2025-04-17', 'Jueves Santo',                      'NACIONAL'),
('2025-04-18', 'Viernes Santo',                     'NACIONAL'),
('2025-05-01', 'Día del Trabajo',                   'NACIONAL'),
('2025-06-07', 'Batalla de Arica',                  'NACIONAL'),
('2025-06-29', 'San Pedro y San Pablo',             'NACIONAL'),
('2025-07-28', 'Fiestas Patrias',                   'NACIONAL'),
('2025-07-29', 'Fiestas Patrias',                   'NACIONAL'),
('2025-08-30', 'Santa Rosa de Lima',                'NACIONAL'),
('2025-10-08', 'Combate de Angamos',                'NACIONAL'),
('2025-11-01', 'Día de Todos los Santos',           'NACIONAL'),
('2025-12-08', 'Inmaculada Concepción',             'NACIONAL'),
('2025-12-25', 'Navidad',                           'NACIONAL');

-- Asistencias de ejemplo para hoy (para probar estado-dia)
INSERT INTO asistencias (usuario_app_id, sede_id, horario_sede_id, fecha, estado_diario) VALUES
(1, 1, 1, CURDATE(), 'PENDIENTE'),
(2, 1, 2, CURDATE(), 'PENDIENTE'),
(3, 2, 4, CURDATE(), 'PENDIENTE');


