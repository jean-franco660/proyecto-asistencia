<?php
use App\Controllers\AuthController;
use App\Controllers\DashboardController;

// Ruta raíz → redirige al login
$router->get('/', [AuthController::class , 'loginForm']);

// Autenticación
$router->get('/login', [AuthController::class , 'loginForm']);
$router->post('/login', [AuthController::class , 'login']);
$router->get('/logout', [AuthController::class , 'logout']);

// Dashboard (protegido — el controlador verifica la sesión)
$router->get('/dashboard', [DashboardController::class , 'index']);
