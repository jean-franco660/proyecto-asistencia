<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

class DashboardController
{
    /**
     * GET /dashboard
     * Muestra el panel principal. Requiere sesión activa.
     */
    public function index(Request $req): void
    {
        // Guard: si no hay sesión, redirige al login
        if (!Session::has('user')) {
            Response::redirect('/login');
        }

        $user = Session::get('user');

        View::render('dashboard.index', [
            'user' => $user,
        ]);
    }
}
