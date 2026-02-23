<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\UsuarioWeb;

class AuthController
{
    /**
     * GET /login
     * Muestra el formulario de login.
     * Si ya hay sesión activa, redirige al dashboard.
     */
    public function loginForm(Request $req): void
    {
        if (Session::has('user')) {
            Response::redirect('/dashboard');
        }

        View::render('auth.login');
    }

    /**
     * POST /login
     * Procesa el formulario de login.
     */
    public function login(Request $req): void
    {
        $email = trim((string)$req->input('email', ''));
        $password = (string)$req->input('password', '');

        // Validación básica
        if (empty($email) || empty($password)) {
            Session::flash('error', 'Email y contraseña son obligatorios.');
            Response::redirect('/login');
        }

        $model = new UsuarioWeb();
        $usuario = $model->findByEmail($email);

        // Verifica si el usuario existe y la contraseña es correcta
        // password_verify() compara con el hash guardado en BD (creado con password_hash)
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            Session::flash('error', 'Correo o contraseña incorrectos.');
            Response::redirect('/login');
        }

        // Guardar datos del usuario en sesión (sin el password)
        unset($usuario['password']);
        Session::set('user', $usuario);

        Response::redirect('/dashboard');
    }

    /**
     * GET /logout
     * Cierra la sesión y redirige al login.
     */
    public function logout(Request $req): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
