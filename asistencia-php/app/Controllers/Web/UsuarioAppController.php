<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioAppController
{
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** GET /v1/web/usuarios-app — listar trabajadores con su sede y horario */
    public function index(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $search = $req->query('search');

        $sql = "
            SELECT u.id, u.codigo_empleado, u.nombres, u.apellido_paterno, u.apellido_materno,
                   u.email, u.dni, u.estado,
                   uas.cargo, s.nombre AS sede_nombre, hs.nombre_turno
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s              ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs     ON hs.id = uas.horario_sede_id
            WHERE 1=1
        ";
        $params = [];

        if ($sedeId) {
            $sql .= " AND uas.sede_id = :sid";
            $params[':sid'] = (int) $sedeId;
        }
        if ($search) {
            $sql .= " AND (u.nombres LIKE :q OR u.apellido_paterno LIKE :q OR u.codigo_empleado LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $sql .= " ORDER BY u.apellido_paterno, u.nombres";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/usuarios-app/{id} */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT u.*, uas.cargo, uas.sede_id, uas.horario_sede_id,
                   s.nombre AS sede_nombre, hs.nombre_turno
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s              ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs     ON hs.id = uas.horario_sede_id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Trabajador no encontrado');
        unset($user['password']);
        Response::success($user);
    }

    /** POST /v1/web/usuarios-app — crear trabajador */
    public function store(Request $req): void
    {
        $nombres    = (string) $req->input('nombres');
        $apPaterno  = (string) $req->input('apellido_paterno');
        $apMaterno  = (string) $req->input('apellido_materno', '');
        $codigo     = (string) $req->input('codigo_empleado');
        $email      = strtolower(trim((string) $req->input('email')));
        $dni        = (string) $req->input('dni', '');
        $password   = (string) $req->input('password');

        $errors = [];
        if (!$nombres)   $errors[] = 'nombres es requerido';
        if (!$apPaterno) $errors[] = 'apellido_paterno es requerido';
        if (!$codigo)    $errors[] = 'codigo_empleado es requerido';
        if (!$email)     $errors[] = 'email es requerido';
        if (!$password)  $errors[] = 'password es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar unicidad
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE email = ? OR codigo_empleado = ?");
        $stmt->execute([$email, $codigo]);
        if ($stmt->fetch()) Response::error('El email o código de empleado ya existe', 422);

        $stmt = $this->db->prepare("
            INSERT INTO usuarios_app
                (nombres, apellido_paterno, apellido_materno, codigo_empleado, email, dni, password, estado)
            VALUES (:n, :ap, :am, :cod, :email, :dni, :pwd, 'ACTIVO')
        ");
        $stmt->execute([
            ':n'    => $nombres,
            ':ap'   => $apPaterno,
            ':am'   => $apMaterno,
            ':cod'  => $codigo,
            ':email'=> $email,
            ':dni'  => $dni,
            ':pwd'  => password_hash($password, PASSWORD_BCRYPT),
        ]);

        Response::success(['id' => $this->db->lastInsertId()], 'Trabajador creado correctamente', 201);
    }

    /** PUT /v1/web/usuarios-app/{id} — actualizar datos */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::notFound('Trabajador no encontrado');

        $campos = [];
        $params = [];
        $permitidos = ['nombres','apellido_paterno','apellido_materno','email','dni'];
        foreach ($permitidos as $campo) {
            if ($req->input($campo) !== null) {
                $campos[]          = "`{$campo}` = ?";
                $params[]          = $req->input($campo);
            }
        }
        if (empty($campos)) Response::unprocessable('No hay campos a actualizar');

        $params[] = $id;
        $this->db->prepare("UPDATE usuarios_app SET " . implode(', ', $campos) . " WHERE id = ?")->execute($params);
        Response::success(null, 'Trabajador actualizado correctamente');
    }

    /** PATCH /v1/web/usuarios-app/{id}/estado — activar/desactivar */
    public function cambiarEstado(Request $req): void
    {
        $id     = (int) $req->param('id');
        $estado = (string) $req->input('estado'); // ACTIVO | INACTIVO

        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido. Use ACTIVO o INACTIVO');

        $stmt = $this->db->prepare("UPDATE usuarios_app SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }

    /** PATCH /v1/web/usuarios-app/{id}/horario — asignar sede y horario */
    public function asignarHorario(Request $req): void
    {
        $id        = (int) $req->param('id');
        $sedeId    = (int) $req->input('sede_id');
        $horarioId = (int) $req->input('horario_sede_id');
        $cargo     = (string) $req->input('cargo', '');

        if (!$sedeId || !$horarioId) Response::unprocessable('sede_id y horario_sede_id son requeridos');

        // FIX Bug #10: Las dos operaciones DML (desactivar anterior + crear nueva)
        // no estaban en transacción. Si el INSERT fallaba, el trabajador quedaba
        // sin asignación porque el UPDATE ya se había ejecutado.
        $this->db->beginTransaction();
        try {
            // Desactivar asignación anterior
            $this->db->prepare("UPDATE usuario_app_sede SET estado = 'INACTIVO' WHERE usuario_app_id = ?")->execute([$id]);

            // Crear nueva asignación
            $this->db->prepare("
                INSERT INTO usuario_app_sede (usuario_app_id, sede_id, horario_sede_id, cargo, estado)
                VALUES (?, ?, ?, ?, 'ACTIVO')
            ")->execute([$id, $sedeId, $horarioId, $cargo]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioAppController::asignarHorario] Error: ' . $e->getMessage());
            Response::error('Error al asignar sede y horario. Intente nuevamente.', 500);
        }

        Response::success(null, 'Sede y horario asignados correctamente');
    }

    /** DELETE /v1/web/usuarios-app/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $this->db->prepare("UPDATE usuarios_app SET estado = 'INACTIVO' WHERE id = ?")->execute([$id]);
        Response::success(null, 'Trabajador desactivado correctamente');
    }
}