<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Asistencia;
use App\Models\AsistenciaDiaria;

/**
 * AsistenciaWebController - Visualización y revisión de asistencias en el panel admin
 */
class AsistenciaWebController
{
    private Response $response;
    private Asistencia $model;
    private AsistenciaDiaria $modelDiaria;

    public function __construct()
    {
        $this->response    = new Response();
        $this->model       = new Asistencia();
        $this->modelDiaria = new AsistenciaDiaria();
    }

    /** GET /v1/web/asistencias */
    public function index(Request $request): void
    {
        $usuarioId = $request->query('usuario_id');
        $fecha     = $request->query('fecha');

        if ($usuarioId) {
            $data = $this->model->porUsuario((int)$usuarioId, $fecha ?: null);
        } else {
            $data = $this->model->all('fecha DESC');
        }

        $this->response->success($data);
    }

    /** GET /v1/web/asistencias/{id} */
    public function show(Request $request): void
    {
        $id   = (int)$request->param('id');
        $item = $this->model->find($id);

        if (!$item) {
            $this->response->notFound('Registro de asistencia no encontrado.');
        }

        $this->response->success($item);
    }

    /** GET /v1/web/asistencias/semana */
    public function resumenSemanal(Request $request): void
    {
        $sedeId = $request->query('sede_id');
        $data   = $this->model->resumenSemana($sedeId ? (int)$sedeId : null);
        $this->response->success($data);
    }

    /** GET /v1/web/asistencias/exportar */
    public function exportar(Request $request): void
    {
        $sedeId = $request->query('sede_id');
        $desde  = $request->query('desde', date('Y-m-01'));
        $hasta  = $request->query('hasta', date('Y-m-d'));

        $data = $this->model->exportar($sedeId ? (int)$sedeId : null, $desde, $hasta);
        $this->response->success($data);
    }

    /** PUT /v1/web/asistencias/{id}/review */
    public function updateReview(Request $request): void
    {
        $id   = (int)$request->param('id');
        $item = $this->model->find($id);

        if (!$item) {
            $this->response->notFound('Registro de asistencia no encontrado.');
        }

        $body = $request->getBody();
        $this->model->update($id, array_filter([
            'presente'    => $body['presente']    ?? null,
            'observacion' => $body['observacion'] ?? null,
        ], fn($v) => $v !== null));

        $this->response->success($this->model->find($id), 'Asistencia revisada.');
    }
}
