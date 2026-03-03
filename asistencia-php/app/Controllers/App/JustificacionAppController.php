<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\Justificacion;

/**
 * JustificacionAppController - Gestión de justificaciones desde la app móvil
 */
class JustificacionAppController
{
    private Response $response;
    private Justificacion $model;

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new Justificacion();
    }

    /**
     * GET /v1/app/justificaciones
     * Lista las justificaciones del usuario autenticado
     */
    public function index(Request $request): void
    {
        $userId = (int)$request->getAttribute('auth_user_id');
        $data   = $this->model->porUsuario($userId);
        $this->response->success($data);
    }

    /**
     * GET /v1/app/justificaciones/{id}
     */
    public function show(Request $request): void
    {
        $id     = (int)$request->param('id');
        $userId = (int)$request->getAttribute('auth_user_id');
        $item   = $this->model->find($id);

        if (!$item || (int)$item['usuario_id'] !== $userId) {
            $this->response->notFound('Justificación no encontrada.');
        }

        $this->response->success($item);
    }

    /**
     * POST /v1/app/justificaciones
     * El trabajador envía una justificación
     */
    public function store(Request $request): void
    {
        $userId = (int)$request->getAttribute('auth_user_id');
        $body   = $request->getBody();

        if (empty($body['motivo'])) {
            $this->response->validationError(['motivo' => 'El motivo es obligatorio.']);
        }

        $id   = $this->model->create([
            'usuario_id'     => $userId,
            'asistencia_id'  => $body['asistencia_id']  ?? null,
            'motivo'         => $body['motivo'],
            'descripcion'    => $body['descripcion']    ?? null,
            'fecha'          => $body['fecha']           ?? date('Y-m-d'),
            'estado'         => 'pendiente',
        ]);
        $item = $this->model->find((int)$id);

        $this->response->success($item, 'Justificación enviada.', 201);
    }

    /**
     * DELETE /v1/app/justificaciones/{id}
     * Solo si aún está pendiente
     */
    public function destroy(Request $request): void
    {
        $id     = (int)$request->param('id');
        $userId = (int)$request->getAttribute('auth_user_id');
        $item   = $this->model->find($id);

        if (!$item || (int)$item['usuario_id'] !== $userId) {
            $this->response->notFound('Justificación no encontrada.');
        }

        if ($item['estado'] !== 'pendiente') {
            $this->response->error('Solo se pueden eliminar justificaciones pendientes.', 403);
        }

        $this->model->delete($id);
        $this->response->success(null, 'Justificación eliminada.');
    }
}
