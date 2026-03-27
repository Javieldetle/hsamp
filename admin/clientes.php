<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";

// --- PROCESO AJAX ---
if (isset($_GET["ajax"]) && $_GET["ajax"] == "1") {
    header("Content-Type: application/json; charset=utf-8");
    $accion = $_POST["accion"] ?? "";
    try {
        if ($accion === "crear") {
            $nombre = trim($_POST["nombre"] ?? "");
            $apellidos = trim($_POST["apellidos"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $telefono = trim($_POST["telefono"] ?? "");
            $documento = trim($_POST["documento"] ?? "");
            $ciudad = trim($_POST["ciudad"] ?? "");
            $provincia = trim($_POST["provincia"] ?? "");
            $direccion = trim($_POST["direccion"] ?? "");
            $cp = trim($_POST["cp"] ?? "");
            $pais = trim($_POST["pais"] ?? "");
            $fecha_nacimiento = trim($_POST["fecha_nacimiento"] ?? "");

            if ($nombre === "" || $apellidos === "" || $email === "") {
                throw new Exception("Nombre, apellidos y email son obligatorios.");
            }
            if ($fecha_nacimiento === "") {
                throw new Exception("La fecha de nacimiento es obligatoria.");
            }

            $hoy = new DateTime();
            $fechaNac = new DateTime($fecha_nacimiento);
            $edad = $hoy->diff($fechaNac)->y;
            if ($edad < 18) {
                throw new Exception("No se puede registrar como cliente a una persona menor de 18 anos.");
            }

            $conexion->beginTransaction();

            $stPersona = $conexion->prepare("SELECT id_persona FROM persona WHERE email = ? LIMIT 1");
            $stPersona->execute([$email]);
            $persona = $stPersona->fetch(PDO::FETCH_ASSOC);

            if ($persona) {
                $id_persona = (int)$persona["id_persona"];

                $stCliente = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_persona = ? LIMIT 1");
                $stCliente->execute([$id_persona]);
                $cliente = $stCliente->fetch(PDO::FETCH_ASSOC);

                if ($cliente) {
                    throw new Exception("Ya existe un cliente con ese email.");
                }

                $stUpd = $conexion->prepare("UPDATE persona SET nombre=:n, apellidos=:a, telefono=:t, documento=:d, ciudad=:c, provincia=:prov, direccion=:dir, cp=:cp, pais=:pais, fecha_nacimiento=:fn WHERE id_persona=:id");
                $stUpd->execute([
                    ":n" => $nombre, ":a" => $apellidos, ":t" => $telefono,
                    ":d" => $documento, ":c" => $ciudad, ":prov" => $provincia, ":dir" => $direccion,
                    ":cp" => $cp, ":pais" => $pais, ":fn" => ($fecha_nacimiento !== "" ? $fecha_nacimiento : null),
                    ":id" => $id_persona
                ]);
            } else {
                $stInsPersona = $conexion->prepare("INSERT INTO persona (nombre, apellidos, email, telefono, documento, ciudad, provincia, direccion, cp, pais, fecha_nacimiento) VALUES (:n, :a, :e, :t, :d, :c, :prov, :dir, :cp, :pais, :fn)");
                $stInsPersona->execute([
                    ":n" => $nombre, ":a" => $apellidos, ":e" => $email, ":t" => $telefono,
                    ":d" => $documento, ":c" => $ciudad, ":prov" => $provincia, ":dir" => $direccion,
                    ":cp" => $cp, ":pais" => $pais, ":fn" => ($fecha_nacimiento !== "" ? $fecha_nacimiento : null)
                ]);
                $id_persona = (int)$conexion->lastInsertId();
            }

            $stInsCliente = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (:idp)");
            $stInsCliente->execute([":idp" => $id_persona]);

            $conexion->commit();
            echo json_encode(["ok" => true, "mensaje" => "Cliente creado correctamente"]);
        } elseif ($accion === "editar") {
            $sql = "UPDATE persona SET nombre=:n, apellidos=:a, email=:e, telefono=:t, documento=:d, ciudad=:c, provincia=:prov, direccion=:dir WHERE id_persona=:id";
            $st = $conexion->prepare($sql);
            $st->execute([
                ":n" => $_POST["nombre"], ":a" => $_POST["apellidos"],
                ":e" => $_POST["email"], ":t" => $_POST["telefono"],
                ":d" => $_POST["documento"], ":c" => $_POST["ciudad"], ":prov" => $_POST["provincia"],
                ":dir" => $_POST["direccion"], ":id" => $_POST["id_persona"]
            ]);
            echo json_encode(["ok" => true, "mensaje" => "Cliente actualizado"]);
        } elseif ($accion === "password") {
            $pass = password_hash($_POST["password"], PASSWORD_BCRYPT);
            $sql = "UPDATE usuario SET password_hash = :p WHERE id_persona = :id";
            $st = $conexion->prepare($sql);
            $st->execute([":p" => $pass, ":id" => $_POST["id_persona"]]);
            echo json_encode(["ok" => true, "mensaje" => "Contrasena actualizada"]);
        } elseif ($accion === "eliminar") {
            $id_p = (int)$_POST["id_persona"];

            $conexion->beginTransaction();
            $st1 = $conexion->prepare("DELETE FROM cliente WHERE id_persona = ?");
            $st1->execute([$id_p]);
            $st2 = $conexion->prepare("DELETE FROM usuario WHERE id_persona = ?");
            $st2->execute([$id_p]);
            $st3 = $conexion->prepare("DELETE FROM persona WHERE id_persona = ?");
            $st3->execute([$id_p]);

            $conexion->commit();
            echo json_encode(["ok" => true, "mensaje" => "Cliente eliminado por completo"]);
        }
    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        echo json_encode(["ok" => false, "mensaje" => "Error: " . $e->getMessage()]);
    }
    exit;
}

include "includes/cabecera_admin.php";
$sql = "SELECT c.id_cliente, p.* FROM cliente c INNER JOIN persona p ON p.id_persona = c.id_persona ORDER BY c.id_cliente DESC";
$clientes = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Gestion de Clientes</h2>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="bi bi-plus-lg"></i> Nuevo Cliente
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Alta</th>
                        <th class="text-end px-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                    <tr class="text-center">
                        <td><?php echo $c["id_cliente"]; ?></td>
                        <td class="text-start"><?php echo htmlspecialchars($c["nombre"] . " " . $c["apellidos"]); ?></td>
                        <td><?php echo htmlspecialchars($c["email"]); ?></td>
                        <td><?php echo htmlspecialchars($c["telefono"]); ?></td>
                        <td><?php echo date("d/m/Y", strtotime($c["creado_en"])); ?></td>
                        <td class="text-end px-4">
                            <div class="btn-group shadow-sm">
                                <button type="button" class="btn btn-sm btn-primary btn-editar"
                                        data-persona='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>'>
                                    Editar
                                </button>
                                <button type="button" class="btn btn-sm btn-dark btn-pass"
                                        data-id="<?php echo $c["id_persona"]; ?>">
                                    Clave
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-eliminar"
                                        data-id="<?php echo $c["id_persona"]; ?>">
                                    Borrar
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formCrear" class="modal-content">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header"><h5>Nuevo Cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3 text-start">
                <div class="col-md-6"><label class="form-label small fw-bold">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Telefono</label><input type="text" name="telefono" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">DNI / NIE</label><input type="text" name="documento" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Ciudad</label><input type="text" name="ciudad" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Provincia</label><input type="text" name="provincia" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">CP</label><input type="text" name="cp" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Pais</label><input type="text" name="pais" class="form-control" value="Espana"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" required></div>
                <div class="col-md-12"><label class="form-label small fw-bold">Direccion</label><input type="text" name="direccion" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success w-100">Guardar Cliente</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formEditar" class="modal-content">
            <input type="hidden" name="id_persona" id="edit_id">
            <input type="hidden" name="accion" value="editar">
            <div class="modal-header"><h5>Editar Datos del Cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3 text-start">
                <div class="col-md-6"><label class="form-label small fw-bold">Nombre</label><input type="text" name="nombre" id="edit_nom" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Apellidos</label><input type="text" name="apellidos" id="edit_ape" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Email</label><input type="email" name="email" id="edit_ema" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Telefono</label><input type="text" name="telefono" id="edit_tel" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">DNI / NIE</label><input type="text" name="documento" id="edit_doc" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Ciudad</label><input type="text" name="ciudad" id="edit_ciu" class="form-control"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Provincia</label><input type="text" name="provincia" id="edit_prov" class="form-control"></div>
                <div class="col-md-12"><label class="form-label small fw-bold">Direccion</label><input type="text" name="direccion" id="edit_dir" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar Cambios</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form id="formPass" class="modal-content">
            <input type="hidden" name="id_persona" id="pass_id">
            <input type="hidden" name="accion" value="password">
            <div class="modal-header"><h5>Nueva Contrasena</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="password" name="password" id="new_password" class="form-control" placeholder="Minimo 10 caracteres" required>
                <div class="form-text mt-2 small">Debe incluir Mayuscula, Numero y Simbolo (+, @, #, $).</div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark w-100">Cambiar Clave</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/clientes_accion.js?v=20260327_1"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const formCrear = document.getElementById("formCrear");
    if (!formCrear) return;

    formCrear.addEventListener("submit", async function (e) {
        e.preventDefault();
        const fechaNac = formCrear.querySelector("input[name='fecha_nacimiento']").value;
        if (fechaNac) {
            const hoy = new Date();
            const fn = new Date(fechaNac + "T00:00:00");
            let edad = hoy.getFullYear() - fn.getFullYear();
            const m = hoy.getMonth() - fn.getMonth();
            if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) {
                edad--;
            }
            if (edad < 18) {
                Swal.fire({
                    icon: "warning",
                    title: "Edad no permitida",
                    text: "No se puede registrar como cliente a una persona menor de 18 anos.",
                    confirmButtonColor: "#0d6efd",
                    confirmButtonText: "Aceptar"
                });
                return;
            }
        }

        const res = await fetch("clientes.php?ajax=1", { method: "POST", body: new FormData(formCrear) });
        const r = await res.json();
        Swal.fire({
            icon: r.ok ? "success" : "error",
            title: r.ok ? "Cliente creado" : "No se pudo crear",
            text: r.mensaje,
            confirmButtonColor: "#0d6efd",
            confirmButtonText: "Aceptar"
        }).then(() => {
            if (r.ok) {
                const modalEl = document.getElementById("modalCrear");
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                location.reload();
            }
        });
    });
});
</script>
<?php include "includes/pie_admin.php"; ?>
