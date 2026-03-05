<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class FeriadoController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function rol(): string {
        return $_REQUEST['auth_user']['rol'] ?? '';
    }

    private function esAdmin(): bool {
        return in_array($this->rol(), ['super_admin', 'administrador']);
    }

    /** GET /v1/web/feriados — listar feriados */
    public function index(Request $req): void
    {
        $tipo   = $req->query('tipo');   // nacional | sede
        $sedeId = $req->query('sede_id');

        $sql    = "SELECT * FROM feriados WHERE activo = 1";
        $params = [];

        if ($tipo) {
            $sql      .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($sedeId) {
            $sql      .= " AND (tipo = 'nacional' OR sede_id = :sid)";
            $params[':sid'] = (int) $sedeId;
        }

        $sql .= " ORDER BY mes, dia";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /** POST /v1/web/feriados — crear feriado */
    public function store(Request $req): void
    {
        $tipo        = (string) $req->input('tipo');        // nacional | sede
        $sedeId      = $req->input('sede_id');
        $descripcion = (string) $req->input('descripcion');
        $dia         = (int) $req->input('dia');
        $mes         = (int) $req->input('mes');

        $errors = [];
        if (!in_array($tipo, ['nacional', 'sede'])) $errors[] = 'tipo inválido (nacional | sede)';
        if ($tipo === 'sede' && !$sedeId)            $errors[] = 'sede_id requerido para tipo sede';
        if (!$descripcion)                           $errors[] = 'descripcion es requerida';
        if ($dia < 1 || $dia > 31)                  $errors[] = 'dia inválido (1-31)';
        if ($mes < 1 || $mes > 12)                  $errors[] = 'mes inválido (1-12)';

        // Solo admins pueden crear feriados nacionales
        if ($tipo === 'nacional' && !$this->esAdmin())
            Response::error('Solo administradores pueden crear feriados nacionales', 403);

        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar duplicado
        $stmt = $this->db->prepare("
            SELECT id FROM feriados WHERE tipo = :tipo AND dia = :dia AND mes = :mes
              AND (sede_id = :sid OR (sede_id IS NULL AND :sid2 IS NULL))
        ");
        $stmt->execute([':tipo' => $tipo, ':dia' => $dia, ':mes' => $mes,
                        ':sid' => $sedeId, ':sid2' => $sedeId]);
        if ($stmt->fetch()) Response::error('Ya existe un feriado con esa fecha', 422);

        $fecha = date('Y') . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
        $this->db->prepare("
            INSERT INTO feriados (tipo, sede_id, descripcion, dia, mes, fecha, activo)
            VALUES (:tipo, :sid, :desc, :dia, :mes, :fecha, 1)
        ")->execute([
            ':tipo'  => $tipo,
            ':sid'   => $sedeId ?: null,
            ':desc'  => $descripcion,
            ':dia'   => $dia,
            ':mes'   => $mes,
            ':fecha' => $fecha,
        ]);

        Response::success(['id' => $this->db->lastInsertId()], 'Feriado creado correctamente', 201);
    }

    /** DELETE /v1/web/feriados/{id} — eliminar (soft delete) */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');

        $stmt = $this->db->prepare("SELECT tipo FROM feriados WHERE id = ?");
        $stmt->execute([$id]);
        $feriado = $stmt->fetch();
        if (!$feriado) Response::notFound('Feriado no encontrado');

        if ($feriado['tipo'] === 'nacional' && !$this->esAdmin())
            Response::error('Solo administradores pueden eliminar feriados nacionales', 403);

        $this->db->prepare("UPDATE feriados SET activo = 0 WHERE id = ?")->execute([$id]);
        Response::success(null, 'Feriado eliminado correctamente');
    }
}