<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Sede;

class SedeWebController
{
    private Sede $model;

    public function __construct() {
        $this->model = new Sede();
    }

    /**
     * GET /v1/web/sedes
     * Filtros: ?search=&sort_by=id&sort_order=asc&per_page=20
     * Admin ve todas. Supervisor ve solo las suyas (ver misSedes).
     */
    public function index(Request $req): void
    {
        $search  = $req->query('search');
        $sortBy  = in_array($req->query('sort_by'), ['id','nombre','distrito','created_at'])
                    ? $req->query('sort_by') : 'id';
        $order   = $req->query('sort_order', 'asc') === 'desc' ? 'DESC' : 'ASC';
        $perPage = (int) $req->query('per_page', 20);
        $offset  = ((int) $req->query('page', 1) - 1) * $perPage;

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($search) {
            $where .= ' AND (nombre LIKE :s OR codigo_sede LIKE :s2)';
            $params[':s']  = "%$search%";
            $params[':s2'] = "%$search%";
        }

        $stmt = $this->model->db()->prepare(
            "SELECT * FROM sedes $where ORDER BY $sortBy $order LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();

        Response::success($stmt->fetchAll());
    }

    /**
     * GET /v1/web/sedes/mis-sedes
     * Admin ve todas. Supervisor ve solo sus sedes vigentes.
     */
    public function misSedes(Request $req): void
    {
        $authUser = $_REQUEST['auth_user'];
        $rol    = $authUser['rol'] ?? '';
        $userId = (int) ($authUser['sub'] ?? 0);

        if (in_array($rol, ['super_admin', 'administrador'])) {
            Response::success($this->model->all());
        } else {
            // Supervisor: solo sus sedes vigentes
            $stmt = $this->model->db()->prepare("
                SELECT s.* FROM sedes s
                INNER JOIN usuario_web_sede uws ON s.id = uws.sede_id
                WHERE uws.usuario_web_id = :uid
                  AND uws.activo = 1
                  AND (uws.fecha_fin IS NULL OR uws.fecha_fin >= CURDATE())
                  AND s.deleted_at IS NULL
            ");
            $stmt->execute([':uid' => $userId]);
            Response::success($stmt->fetchAll());
        }
    }

    /** POST /v1/web/sedes */
    public function store(Request $req): void
    {
        $data = $req->only([
            'codigo_sede','nombre','rubro',
            'distrito','direccion',
            'latitud','longitud','radio'
        ]);

        $errors = [];
        if (empty($data['codigo_sede'])) $errors[] = 'codigo_sede es requerido';
        if (empty($data['nombre']))      $errors[] = 'nombre es requerido';
        if (!isset($data['latitud']))    $errors[] = 'latitud es requerida';
        if (!isset($data['longitud']))   $errors[] = 'longitud es requerida';
        if (!isset($data['radio']))      $errors[] = 'radio es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        $id = $this->model->create($data);
        Response::success($this->model->find($id), 'Sede creada correctamente', 201);
    }

    /** GET /v1/web/sedes/{id} */
    public function show(Request $req): void
    {
        $sede = $this->model->find((int) $req->param('id'));
        if (!$sede) Response::notFound('Sede no encontrada');
        Response::success($sede);
    }

    /** PUT /v1/web/sedes/{id} */
    public function update(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) Response::notFound('Sede no encontrada');

        $data = $req->only(['nombre','rubro','distrito','direccion','latitud','longitud','radio']);
        $this->model->update($id, $data);
        Response::success($this->model->find($id), 'Sede actualizada correctamente');
    }

    /** DELETE /v1/web/sedes/{id} */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) Response::notFound('Sede no encontrada');

        try {
            $this->model->delete($id);
            Response::success(null, 'Sede eliminada correctamente');
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error(
                    'No se puede eliminar: la sede tiene trabajadores, horarios o asistencias asociadas',
                    409
                );
            }
            Response::error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }
}