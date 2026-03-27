<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";

// --- PROCESO AJAX ---
if (isset($_GET["ajax"]) && $_GET["ajax"] == "1") {
    header("Content-Type: application/json; charset=utf-8");
    $accion = $_POST["accion"] ?? "";
    try {
        $conexion->beginTransaction();

        if ($accion === "crear") {
            // 1. Insertar en Persona
            $sqlP = "INSERT INTO persona (nombre, apellidos, email, telefono, documento, ciudad, direccion, cp, pais, fecha_nacimiento, creado_en) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $conexion->prepare($sqlP)->execute([
                $_POST["nombre"],
                $_POST["apellidos"],
                $_POST["email"],
                $_POST["telefono"],
                $_POST["documento"],
                $_POST["ciudad"],
                $_POST["direccion"],
                $_POST["cp"],
                $_POST["pais"],
                $_POST["fecha_nacimiento"]
            ]);
            $id_persona = $conexion->lastInsertId();

            // 2. Insertar en Usuario
            $pass = password_hash($_POST["password"], PASSWORD_BCRYPT);
            $sqlU = "INSERT INTO usuario (id_persona, username, password_hash, rol, activo, creado_en) VALUES (?, ?, ?, 'RECEPCION', 1, NOW())";
            $conexion->prepare($sqlU)->execute([$id_persona, $_POST["email"], $pass]);

            // 3. Insertar en Empleado (salario incluido)
            $sqlE = "INSERT INTO empleado (id_persona, fecha_contratacion, puesto, salario, activo) VALUES (?, CURDATE(), 'RECEPCION', ?, 1)";
            $conexion->prepare($sqlE)->execute([$id_persona, $_POST["salario"]]);

            echo json_encode(["ok" => true, "mensaje" => "Empleado creado con éxito"]);
        } elseif ($accion === "editar") {
            // Actualizar Persona
            $sqlP = "UPDATE persona SET nombre=?, apellidos=?, email=?, telefono=?, documento=?, ciudad=?, direccion=?, cp=?, pais=?, fecha_nacimiento=? WHERE id_persona=?";
            $conexion->prepare($sqlP)->execute([
                $_POST["nombre"],
                $_POST["apellidos"],
                $_POST["email"],
                $_POST["telefono"],
                $_POST["documento"],
                $_POST["ciudad"],
                $_POST["direccion"],
                $_POST["cp"],
                $_POST["pais"],
                $_POST["fecha_nacimiento"],
                $_POST["id_persona"]
            ]);

            // Actualizar Salario
            $sqlE = "UPDATE empleado SET salario=? WHERE id_persona=?";
            $conexion->prepare($sqlE)->execute([$_POST["salario"], $_POST["id_persona"]]);

            echo json_encode(["ok" => true, "mensaje" => "Datos actualizados correctamente"]);
        } elseif ($accion === "password") {
            $pass = password_hash($_POST["password"], PASSWORD_BCRYPT);
            $sql = "UPDATE usuario SET password_hash = ? WHERE id_persona = ?";
            $conexion->prepare($sql)->execute([$pass, $_POST["id_persona"]]);
            echo json_encode(["ok" => true, "mensaje" => "Contraseña actualizada con éxito"]);
        } elseif ($accion === "eliminar") {
            $id_persona = $_POST["id_persona"];

            // Borrar en orden para evitar errores de clave foránea
            $conexion->prepare("DELETE FROM empleado WHERE id_persona = ?")->execute([$id_persona]);
            $conexion->prepare("DELETE FROM usuario WHERE id_persona = ?")->execute([$id_persona]);
            $conexion->prepare("DELETE FROM persona WHERE id_persona = ?")->execute([$id_persona]);

            echo json_encode(["ok" => true, "mensaje" => "Empleado eliminado correctamente"]);
        }

        $conexion->commit();
    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        echo json_encode(["ok" => false, "mensaje" => "Error: " . $e->getMessage()]);
    }
    exit;
}

include "includes/cabecera_admin.php";

$sql = "SELECT e.id_empleado, e.fecha_contratacion, e.salario, p.* FROM empleado e 
        INNER JOIN persona p ON p.id_persona = e.id_persona 
        INNER JOIN usuario u ON u.id_persona = p.id_persona 
        WHERE u.rol = 'RECEPCION' ORDER BY e.id_empleado DESC";
$empleados = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Gestión de Empleados (RECEPCIÓN)</h2>
        <button class="btn btn-primary shadow-sm" id="btnNuevoEmpleado">Nuevo Empleado</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>DNI</th>
                        <th>Salario</th>
                        <th>Contratación</th>
                        <th class="text-end px-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $e): ?>
                        <tr>
                            <td><?php echo $e["id_empleado"]; ?></td>
                            <td class="text-start"><?php echo htmlspecialchars($e["nombre"] . " " . $e["apellidos"]); ?></td>
                            <td><?php echo htmlspecialchars($e["email"]); ?></td>
                            <td><?php echo htmlspecialchars($e["documento"]); ?></td>
                            <td><strong><?php echo number_format($e["salario"], 2); ?>€</strong></td>
                            <td><?php echo date("d/m/Y", strtotime($e["fecha_contratacion"])); ?></td>
                            <td class="text-end px-4">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary btn-editar" data-emp='<?php echo json_encode($e); ?>'>Editar</button>
                                    <button class="btn btn-sm btn-outline-dark btn-pass" data-id="<?php echo $e['id_persona']; ?>">Clave</button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="<?php echo $e['id_persona']; ?>">Borrar</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="formEmpleado" class="modal-content border-0 shadow">
            <input type="hidden" name="id_persona" id="emp_id">
            <input type="hidden" name="accion" id="emp_accion" value="crear">
            <div class="modal-header bg-primary text-white">
                <h5 id="modalTitle">Datos del Empleado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label fw-bold small">Nombre</label><input type="text" name="nombre" id="emp_nom" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label fw-bold small">Apellidos</label><input type="text" name="apellidos" id="emp_ape" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label fw-bold small">Email (Usuario)</label><input type="email" name="email" id="emp_ema" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label fw-bold small">Teléfono</label><input type="text" name="telefono" id="emp_tel" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label fw-bold small">DNI / Documento</label><input type="text" name="documento" id="emp_doc" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label fw-bold small">Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" id="emp_nac" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label fw-bold small">Salario Anual (€)</label><input type="number" step="0.01" name="salario" id="emp_sal" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label fw-bold small">Ciudad</label><input type="text" name="ciudad" id="emp_ciu" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-bold small">CP</label><input type="text" name="cp" id="emp_cp" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-bold small">País</label><input type="text" name="pais" id="emp_pai" class="form-control"></div>
                <div class="col-12"><label class="form-label fw-bold small">Dirección</label><input type="text" name="direccion" id="emp_dir" class="form-control"></div>

                <div class="col-12" id="div_pass">
                    <hr>
                    <label class="form-label fw-bold">Contraseña de acceso</label>
                    <input type="password" name="password" id="emp_pass" class="form-control" placeholder="Mín. 10 caracteres, Mayúscula, Número y Símbolo">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" class="btn btn-primary w-100 py-2">Guardar Empleado</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalPass" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form id="formPass" class="modal-content border-0 shadow">
            <input type="hidden" name="id_persona" id="pass_id">
            <input type="hidden" name="accion" value="password">
            <div class="modal-header bg-dark text-white">
                <h5>Cambiar Clave</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-bold">Nueva Contraseña</label>
                <input type="password" name="password" id="new_password" class="form-control" required placeholder="Regla de 10 caracteres">
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark w-100">Actualizar</button></div>
        </form>
    </div>
</div>

<script src="js/empleados_accion.js"></script>
<?php include "includes/pie_admin.php"; ?>