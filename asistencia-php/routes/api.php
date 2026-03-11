<?php

use App\Controllers\App\AuthAppController;
use App\Controllers\App\AsistenciaAppController;
use App\Controllers\App\SedeAppController;
use App\Controllers\App\JustificacionAppController;
use App\Controllers\App\HorarioAppController;

use App\Controllers\Web\AuthWebController;
use App\Controllers\Web\UsuarioAppController;
use App\Controllers\Web\SedeWebController;
use App\Controllers\Web\HorarioWebController;
use App\Controllers\Web\AsistenciaWebController;
use App\Controllers\Web\JustificacionWebController;
use App\Controllers\Web\FeriadoController;
use App\Controllers\Web\StatsController;

use App\Middleware\AuthAppMiddleware;
use App\Middleware\AuthWebMiddleware;

// ══════════════════════════════════════════════
// APP MÓVIL
// ══════════════════════════════════════════════

// Públicas
$router->post('/v1/app/login', [AuthAppController::class, 'login']);

// Protegidas (JWT tipo 'app')
$router->authAppGet('/v1/app/perfil',                    [AuthAppController::class, 'perfil']);
$router->authAppPost('/v1/app/logout',                   [AuthAppController::class, 'logout']);
$router->authAppGet('/v1/app/sedes',                     [SedeAppController::class, 'index']);

// Asistencia (estáticas ANTES que dinámicas)
$router->authAppPost('/v1/app/asistencia',               [AsistenciaAppController::class, 'store']);
$router->authAppPost('/v1/app/asistencias/sincronizar',  [AsistenciaAppController::class, 'syncMovil']);
$router->authAppGet('/v1/app/estado-dia',                [AsistenciaAppController::class, 'estadoDia']);
$router->authAppGet('/v1/app/asistencia/{usuarioId}',    [AsistenciaAppController::class, 'historial']);

// Horarios de sede
$router->authAppGet('/v1/app/horarios', [HorarioAppController::class, 'obtenerHorarios']);

// Justificaciones
$router->authAppGet('/v1/app/justificaciones',           [JustificacionAppController::class, 'index']);
$router->authAppPost('/v1/app/justificaciones',          [JustificacionAppController::class, 'store']);
$router->authAppGet('/v1/app/justificaciones/{id}',      [JustificacionAppController::class, 'show']);
$router->authAppDelete('/v1/app/justificaciones/{id}',   [JustificacionAppController::class, 'destroy']);

// ══════════════════════════════════════════════
// PANEL WEB
// ══════════════════════════════════════════════

// Públicas
$router->post('/v1/web/login', [AuthWebController::class, 'login']);

// Protegidas (JWT tipo 'web')
$router->authWebPost('/v1/web/logout',                   [AuthWebController::class, 'logout']);
$router->authWebGet('/v1/web/me',                        [AuthWebController::class, 'me']);

// Gestión de trabajadores
$router->authWebGet('/v1/web/usuarios-app',              [UsuarioAppController::class, 'index']);
$router->authWebPost('/v1/web/usuarios-app',             [UsuarioAppController::class, 'store']);
$router->authWebGet('/v1/web/usuarios-app/{id}',         [UsuarioAppController::class, 'show']);
$router->authWebPut('/v1/web/usuarios-app/{id}',         [UsuarioAppController::class, 'update']);
$router->authWebDelete('/v1/web/usuarios-app/{id}',      [UsuarioAppController::class, 'destroy']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/estado',[UsuarioAppController::class, 'cambiarEstado']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/horario',[UsuarioAppController::class, 'asignarHorario']);

// Sedes
$router->authWebGet('/v1/web/sedes',                     [SedeWebController::class, 'index']);
$router->authWebGet('/v1/web/sedes/mis-sedes',           [SedeWebController::class, 'misSedes']);
$router->authWebPost('/v1/web/sedes',                    [SedeWebController::class, 'store']);
$router->authWebGet('/v1/web/sedes/{id}',                [SedeWebController::class, 'show']);
$router->authWebPut('/v1/web/sedes/{id}',                [SedeWebController::class, 'update']);
$router->authWebDelete('/v1/web/sedes/{id}',             [SedeWebController::class, 'destroy']);

// Horarios
$router->authWebGet('/v1/web/horarios',                  [HorarioWebController::class, 'index']);
$router->authWebPost('/v1/web/horarios',                 [HorarioWebController::class, 'store']);
$router->authWebPut('/v1/web/horarios/{id}',             [HorarioWebController::class, 'update']);
$router->authWebDelete('/v1/web/horarios/{id}',          [HorarioWebController::class, 'destroy']);

// Feriados
$router->authWebGet('/v1/web/feriados',                  [FeriadoController::class, 'index']);
$router->authWebPost('/v1/web/feriados',                 [FeriadoController::class, 'store']);
$router->authWebPut('/v1/web/feriados/{id}',             [FeriadoController::class, 'update']);
$router->authWebDelete('/v1/web/feriados/{id}',          [FeriadoController::class, 'destroy']);

// Asistencias (estáticas ANTES que dinámicas)
$router->authWebGet('/v1/web/asistencias/semana',        [AsistenciaWebController::class, 'resumenSemanal']);
$router->authWebGet('/v1/web/asistencias/exportar',      [AsistenciaWebController::class, 'exportar']);
$router->authWebGet('/v1/web/asistencias',               [AsistenciaWebController::class, 'index']);
$router->authWebGet('/v1/web/asistencias/{id}',          [AsistenciaWebController::class, 'show']);
$router->authWebPut('/v1/web/asistencias/{id}/review',   [AsistenciaWebController::class, 'updateReview']);

// Justificaciones
$router->authWebGet('/v1/web/justificaciones',               [JustificacionWebController::class, 'index']);
$router->authWebGet('/v1/web/justificaciones/{id}',          [JustificacionWebController::class, 'show']);
$router->authWebPost('/v1/web/justificaciones/{id}/aprobar', [JustificacionWebController::class, 'aprobar']);
$router->authWebPost('/v1/web/justificaciones/{id}/rechazar',[JustificacionWebController::class, 'rechazar']);
$router->authWebDelete('/v1/web/justificaciones/{id}',       [JustificacionWebController::class, 'destroy']);

// Estadísticas
// FIX Bug #12: StatsController solo tiene el método 'dashboard', no 'index'.
$router->authWebGet('/v1/web/stats',                     [StatsController::class, 'dashboard']);