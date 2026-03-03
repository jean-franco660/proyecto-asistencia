<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\Asistencia;
use App\Models\AsistenciaDiaria;

/**
 * AsistenciaAppController - Registro y consulta de asistencia desde la app
 */
class AsistenciaAppController
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

    /**
     * POST /v1/app/asistencia
     * Registra la asistencia diaria (entrada) con coordenadas GPS
     */
    public function store(Request $request): void
    {
        $userId = (int)$request->getAttribute('auth_user_id');
        $body   = $request->getBody();

        if ($this->modelDiaria->hoy($userId)) {
            $this->response->error('Ya registraste tu entrada hoy.', 409);
        }

        $id   = $this->modelDiaria->registrarEntrada(
            $userId,
            $body['latitud']  ?? null,
            $body['longitud'] ?? null
        );
        $data = $this->modelDiaria->find((int)$id);

        $this->response->success($data, 'Entrada registrada.', 201);
    }

    /**
     * GET /v1/app/estado-dia
     * Devuelve el registro de hoy del usuario autenticado
     */
    public function estadoDia(Request $request): void
    {
        $userId = (int)$request->getAttribute('auth_user_id');
        $data   = $this->modelDiaria->hoy($userId);

        $this->response->success($data);
    }

    /**
     * GET /v1/app/asistencia/{usuarioId}
     * Historial de asistencias de un usuario (el propio usuario o admin)
     */
    public function historial(Request $request): void
    {
        $usuarioId = (int)$request->param('usuarioId');
        $fecha     = $request->query('fecha');

        $data = $this->model->porUsuario($usuarioId, $fecha ?: null);

        $this->response->success($data);
    }

    /**
     * POST /v1/app/asistencias/sincronizar
     * Permite sincronizar registros creados offline en la app
     */
    public function syncMovil(Request $request): void
    {
        $userId  = (int)$request->getAttribute('auth_user_id');
        $body    = $request->getBody();
        $records = $body['registros'] ?? [];

        if (empty($records) || !is_array($records)) {
            $this->response->validationError(['registros' => 'Se requiere un array de registros.']);
        }

        $creados  = 0;
        $omitidos = 0;

        foreach ($records as $rec) {
            if (empty($rec['fecha']) || empty($rec['horario_id'])) {
                $omitidos++;
                continue;
            }

            if ($this->model->existeRegistro($userId, (int)$rec['horario_id'], $rec['fecha'])) {
                $omitidos++;
                continue;
            }

            $this->model->create([
                'usuario_id'  => $userId,
                'horario_id'  => $rec['horario_id'],
                'fecha'       => $rec['fecha'],
                'presente'    => $rec['presente']    ?? 1,
                'observacion' => $rec['observacion'] ?? null,
            ]);
            $creados++;
        }

        $this->response->success(
            ['creados' => $creados, 'omitidos' => $omitidos],
            'Sincronización completada.'
        );
    }
}
