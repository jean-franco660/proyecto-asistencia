<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\Sede;
use App\Models\UsuarioApp;

/**
 * SedeAppController - Consulta de sedes desde la app móvil (solo lectura)
 */
class SedeAppController
{
    private Response $response;
    private Sede $model;

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new Sede();
    }

    /**
     * GET /v1/app/sedes
     * Lista las sedes activas disponibles
     */
    public function index(Request $request): void
    {
        $data = $this->model->activas();
        $this->response->success($data);
    }
}
