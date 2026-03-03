<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\HorarioSede;
use App\Models\UsuarioApp;

/**
 * HorarioAppController - Consulta de horarios de la sede desde la app
 */
class HorarioAppController
{
    private Response $response;
    private HorarioSede $model;

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new HorarioSede();
    }

    /**
     * GET /v1/app/horarios-sede
     * Devuelve los horarios de la sede a la que pertenece el usuario
     */
    public function misHorarios(Request $request): void
    {
        $userId  = (int)$request->getAttribute('auth_user_id');
        $usuario = (new UsuarioApp())->find($userId);

        if (!$usuario || empty($usuario['sede_id'])) {
            $this->response->error('El usuario no tiene sede asignada.', 422);
        }

        $data = $this->model->porSede((int)$usuario['sede_id']);
        $this->response->success($data);
    }

    /**
     * GET /v1/app/mis-horarios
     * Alias de misHorarios para compatibilidad
     */
    public function getMisHorarios(Request $request): void
    {
        $this->misHorarios($request);
    }

    /**
     * POST /v1/app/actualizar-horarios
     * Devuelve los horarios actualizados de la sede (para sync offline)
     */
    public function actualizarHorarios(Request $request): void
    {
        $userId  = (int)$request->getAttribute('auth_user_id');
        $usuario = (new UsuarioApp())->find($userId);

        if (!$usuario || empty($usuario['sede_id'])) {
            $this->response->error('El usuario no tiene sede asignada.', 422);
        }

        $data = $this->model->porSede((int)$usuario['sede_id']);
        $this->response->success($data, 'Horarios actualizados.');
    }
}
