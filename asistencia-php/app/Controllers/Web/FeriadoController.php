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

    private function esFechaValida(string $fecha): bool {
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    /** GET /v1/web/feriados — listar feriados */
    public function index(Request $req): void
    {
        $tipo   = $req->query('tipo');   // NACIONAL | LOCAL | EMPRESA
        $sedeId = $req->query('sede_id');

        $sql    = "SELECT * FROM feriados WHERE activo = 1";
        $params = [];

        if ($tipo) {
            $sql      .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($sedeId) {
            $sql      .= " AND (tipo = 'NACIONAL' OR sede_id = :sid)";
            $params[':sid'] = (int) $sedeId;
        }

        $sql .= " ORDER BY fecha ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/feriados/{id} — obtener detalle */
    public function show(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT * FROM feriados WHERE id = ?");
        $stmt->execute([$id]);
        $feriado = $stmt->fetch();
        
        if (!$feriado) Response::notFound('Feriado no encontrado');
        Response::success($feriado);
    }

    /** POST /v1/web/feriados — crear feriado */
    public function store(Request $req): void
    {
        $tipo     = (string) $req->input('tipo');
        $sedeId   = $req->input('sede_id');
        $nombre   = (string) $req->input('nombre');     // ✅ Campo correcto
        $fecha    = (string) $req->input('fecha');      // YYYY-MM-DD

        $errors = [];
        if (!in_array($tipo, ['NACIONAL', 'LOCAL', 'EMPRESA'])) 
            $errors[] = 'tipo inválido (NACIONAL | LOCAL | EMPRESA)';
        if ($tipo === 'EMPRESA' && !$sedeId)            
            $errors[] = 'sede_id requerido para tipo EMPRESA';
        if (!$nombre)                                   
            $errors[] = 'nombre es requerido';
        if (!$fecha || !$this->esFechaValida($fecha))
            $errors[] = 'fecha inválida (formato: YYYY-MM-DD)';

        // Solo admins pueden crear feriados nacionales
        if ($tipo === 'NACIONAL' && !$this->esAdmin())
            Response::error('Solo administradores pueden crear feriados nacionales', 403);

        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar duplicado por fecha
        $stmt = $this->db->prepare("SELECT id FROM feriados WHERE fecha = ?");
        $stmt->execute([$fecha]);
        if ($stmt->fetch()) Response::error('Ya existe un feriado con esa fecha', 422);

        // ✅ INSERT correcto con columnas correctas
        $this->db->prepare("
            INSERT INTO feriados (tipo, sede_id, nombre, fecha, activo)
            VALUES (:tipo, :sid, :nombre, :fecha, 1)
        ")->execute([
            ':tipo'   => $tipo,
            ':sid'    => $sedeId ?: null,
            ':nombre' => $nombre,
            ':fecha'  => $fecha,
        ]);

        Response::success(['id' => $this->db->lastInsertId()], 'Feriado creado correctamente', 201);
    }

    /** PUT /v1/web/feriados/{id} — actualizar feriado */
    public function update(Request $req): void
    {
        $id     = (int) $req->param('id');
        $nombre = (string) $req->input('nombre', '');
        $fecha  = (string) $req->input('fecha', '');
        $activo = (int) $req->input('activo', 1);

        $stmt = $this->db->prepare("SELECT tipo, nombre, fecha FROM feriados WHERE id = ?");
        $stmt->execute([$id]);
        $feriado = $stmt->fetch();
        
        if (!$feriado) Response::notFound('Feriado no encontrado');

        if ($feriado['tipo'] === 'NACIONAL' && !$this->esAdmin())
            Response::error('Solo administradores pueden editar feriados nacionales', 403);

        // Usar valores actuales como default
        $nombre = $nombre ?: $feriado['nombre'];
        $fecha  = $fecha ?: $feriado['fecha'];

        // Validar fecha si se proporciona
        if ($fecha && !$this->esFechaValida($fecha))
            Response::unprocessable('fecha inválida (formato: YYYY-MM-DD)');

        $this->db->prepare("
            UPDATE feriados SET nombre = ?, fecha = ?, activo = ? WHERE id = ?
        ")->execute([$nombre, $fecha, $activo, $id]);

        Response::success(null, 'Feriado actualizado correctamente');
    }

    /** DELETE /v1/web/feriados/{id} — eliminar (soft delete) */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');

        $stmt = $this->db->prepare("SELECT tipo FROM feriados WHERE id = ?");
        $stmt->execute([$id]);
        $feriado = $stmt->fetch();
        if (!$feriado) Response::notFound('Feriado no encontrado');

        if ($feriado['tipo'] === 'NACIONAL' && !$this->esAdmin())
            Response::error('Solo administradores pueden eliminar feriados nacionales', 403);

        $this->db->prepare("UPDATE feriados SET activo = 0 WHERE id = ?")->execute([$id]);
        Response::success(null, 'Feriado eliminado correctamente');
    }
}