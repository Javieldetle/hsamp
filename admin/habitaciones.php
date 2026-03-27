<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";

/* =========================================================
   MODO AJAX: este mismo fichero responde JSON
========================================================= */
if (isset($_GET["ajax"]) && $_GET["ajax"] == "1") {

    header("Content-Type: application/json; charset=utf-8");

    function responder($ok, $mensaje, $data = [])
    {
        echo json_encode([
            "ok" => $ok,
            "mensaje" => $mensaje,
            "data" => $data
        ]);
        exit;
    }

    $accion = trim($_POST["accion"] ?? "");

    if ($accion === "") {
        responder(false, "Acción no válida.");
    }

    try {
        /* ACCIÓN: OBTENER TARIFAS (Nueva) */
        if ($accion === "obtener_tarifas") {
            $id_tipo = (int)($_POST["id_tipo"] ?? 0);
            $sql = "SELECT cantidad_personas, precio_noche FROM tarifa WHERE id_tipo = :id ORDER BY cantidad_personas ASC";
            $st = $conexion->prepare($sql);
            $st->execute([":id" => $id_tipo]);
            $tarifas = $st->fetchAll(PDO::FETCH_ASSOC);
            responder(true, "Tarifas cargadas", $tarifas);
        }

        /* ACCIÓN: CREAR HABITACIÓN */
        if ($accion === "crear") {
            $numero = trim($_POST["numero"] ?? "");
            $id_tipo = (int)($_POST["id_tipo"] ?? 0);
            $piso = trim($_POST["piso"] ?? "");
            $estado = trim($_POST["estado"] ?? "DISPONIBLE");
            $descripcion = trim($_POST["descripcion"] ?? "");
            $precio_noche = (float)($_POST["precio_noche"] ?? 0);

            if ($numero === "" || $id_tipo <= 0) {
                responder(false, "Completa el número y el tipo de habitación.");
            }

            // Duplicado por número
            $sql = "SELECT 1 FROM habitacion WHERE numero = :n LIMIT 1";
            $st = $conexion->prepare($sql);
            $st->execute([":n" => $numero]);
            if ($st->fetchColumn()) {
                responder(false, "Ya existe una habitación con ese número.");
            }

            $conexion->beginTransaction();
            $sql = "INSERT INTO habitacion (numero, id_tipo, piso, estado, descripcion, precio_noche)
                    VALUES (:numero,:id_tipo,:piso,:estado,:descripcion,:precio)";
            $st = $conexion->prepare($sql);
            $st->execute([
                ":numero" => $numero,
                ":id_tipo" => $id_tipo,
                ":piso" => ($piso === "") ? null : (int)$piso,
                ":estado" => $estado,
                ":descripcion" => ($descripcion === "") ? null : $descripcion,
                ":precio" => $precio_noche
            ]);

            $id_hab = (int)$conexion->lastInsertId();

            // Plazas iniciales
            $tipos = $conexion->query("SELECT id_tipo_plaza FROM tipo_plaza")->fetchAll(PDO::FETCH_ASSOC);
            $stIns = $conexion->prepare("INSERT INTO habitacion_plaza (id_habitacion, id_tipo_plaza, cantidad) VALUES (:hab, :tp, 0)");
            foreach ($tipos as $t) {
                $stIns->execute([":hab" => $id_hab, ":tp" => (int)$t["id_tipo_plaza"]]);
            }

            $conexion->commit();
            responder(true, "Habitación creada correctamente.", ["id_habitacion" => $id_hab]);
        }

        /* ACCIÓN: GUARDAR TARIFAS */
        if ($accion === "guardar_tarifas") {
            $id_tipo = (int)($_POST["id_tipo_tarifa"] ?? 0);
            if ($id_tipo <= 0) responder(false, "Tipo no válido.");

            $conexion->beginTransaction();
            for ($i = 1; $i <= 5; $i++) {
                $precio = (float)($_POST["tarifa_$i"] ?? 0);
                $st = $conexion->prepare("UPDATE tarifa SET precio_noche = :p WHERE cantidad_personas = :n AND id_tipo = :t");
                $st->execute([":p" => $precio, ":n" => $i, ":t" => $id_tipo]);
            }
            $conexion->commit();
            responder(true, "Tarifas actualizadas.");
        }

        // ... resto de acciones (eliminar, sumar, restar) ...

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        responder(false, "Error: " . $e->getMessage());
    }
}
/* =========================================================
   MODO NORMAL: mostrar la página HTML
========================================================= */
include "includes/cabecera_admin.php";


$tipos_habitacion = $conexion->query("SELECT id_tipo, nombre FROM tipo_habitacion ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$tipos_plaza = $conexion->query("SELECT id_tipo_plaza, nombre, plazas FROM tipo_plaza ORDER BY id_tipo_plaza ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT h.*, t.nombre AS tipo_nombre FROM habitacion h INNER JOIN tipo_habitacion t ON t.id_tipo = h.id_tipo ORDER BY h.numero ASC";
$habitaciones = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT hp.*, tp.plazas FROM habitacion_plaza hp INNER JOIN tipo_plaza tp ON tp.id_tipo_plaza = hp.id_tipo_plaza";
$plazas_raw = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$plazas = [];
foreach ($plazas_raw as $p) {
    $id = (int)$p["id_habitacion"];
    if (!isset($plazas[$id])) {
        $plazas[$id] = ["total_p" => 0, "total_c" => 0, "cants" => []];
    }
    $plazas[$id]["cants"][(int)$p["id_tipo_plaza"]] = (int)$p["cantidad"];
    $plazas[$id]["total_p"] += ((int)$p["cantidad"] * (int)$p["plazas"]);
    $plazas[$id]["total_c"] += (int)$p["cantidad"];
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0">Gestor de Habitaciones</h2>
            <p class="text-muted">Gestione sus habitaciones y las tarifas globales por ocupación.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTarifas">
                <i class="bi bi-currency-euro"></i> Ajustar Tarifas
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearHabitacion">
                Añadir habitación
            </button>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($habitaciones as $h): $id = (int)$h["id_habitacion"]; ?>
            <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="fw-bold mb-0">Hab. <?php echo htmlspecialchars($h["numero"]); ?></h5>
                                <small class="text-muted">Piso <?php echo htmlspecialchars($h["piso"] ?? '0'); ?></small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="<?php echo $id; ?>">✖</button>
                        </div>

                        <div class="mb-2">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($h["tipo_nombre"]); ?></span>
                            <span class="badge bg-dark"><?php echo $h["estado"]; ?></span>
                        </div>



                        <div class="small mb-1">
                            <i class="bi bi-door-closed"></i> Camas:
                            <b id="total_camas_<?php echo $id; ?>"><?php echo $plazas[$id]["total_c"] ?? 0; ?></b> / 5
                        </div>
                        <div class="small mb-3">
                            <i class="bi bi-people"></i> Capacidad:
                            <b id="total_plazas_<?php echo $id; ?>"><?php echo $plazas[$id]["total_p"] ?? 0; ?></b> personas
                        </div>

                        <div class="border-top pt-2">
                            <?php foreach ($tipos_plaza as $tp):
                                $tp_id = (int)$tp["id_tipo_plaza"];
                                $c = $plazas[$id]["cants"][$tp_id] ?? 0;
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="lh-1">
                                        <div class="small fw-bold"><?php echo $tp["nombre"]; ?></div>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo $tp["plazas"]; ?> plazas</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-plaza" data-accion="restar" data-id-habitacion="<?php echo $id; ?>" data-id-tipo-plaza="<?php echo $tp_id; ?>">-</button>
                                        <span class="btn btn-light disabled fw-bold" style="width:35px" id="cant_<?php echo $id; ?>_<?php echo $tp_id; ?>"><?php echo $c; ?></span>
                                        <button class="btn btn-outline-primary btn-plaza" data-accion="sumar" data-id-habitacion="<?php echo $id; ?>" data-id-tipo-plaza="<?php echo $tp_id; ?>">+</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="modal fade" id="modalCrearHabitacion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formCrearHabitacion" class="modal-content">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5>Nueva Habitación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Número *</label>
                        <input type="text" name="numero" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo *</label>
                        <select name="id_tipo" class="form-select" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($tipos_habitacion as $t) echo "<option value='{$t['id_tipo']}'>{$t['nombre']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Piso</label>
                        <input type="number" name="piso" class="form-control" min="0" max="50">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control">
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success">Guardar Habitación</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalTarifas" tabindex="-1">
        <div class="modal-dialog">
            <form id="formGuardarTarifas" class="modal-content">
                <input type="hidden" name="accion" value="guardar_tarifas">
                <div class="modal-header">
                    <h5>Configuración de Tarifas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">1. Seleccione el Tipo de Habitación</label>
                        <select name="id_tipo_tarifa" id="select_tipo_tarifa" class="form-select" required>
                            <option value="">-- Seleccionar Tipo --</option>
                            <?php foreach ($tipos_habitacion as $t): ?>
                                <option value="<?php echo $t['id_tipo']; ?>"><?php echo $t['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr>
                    <p class="small text-muted">2. Defina los precios por noche para este tipo:</p>

                    <div id="contenedorTarifasDinamicas">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="input-group mb-3">
                                <span class="input-group-text" style="width: 120px;"><?php echo $i; ?> Persona(s)</span>
                                <input type="number" step="0.01" name="tarifa_<?php echo $i; ?>"
                                    id="input_tarifa_<?php echo $i; ?>" class="form-control" value="0.00">
                                <span class="input-group-text">€</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="subit" class="btn btn-primary">Guardar Cammbios del Tipo</button>
                </div>
            </form>
        </div>
    </div>

    <?php include "includes/pie_admin.php"; ?>
    <script src="js/habitaciones_accion.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMensajeTexto">
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    </body>

    </html>

    <script>
document.addEventListener("DOMContentLoaded", function() {
    const URL_AJAX = "habitaciones.php?ajax=1";
    const selectTipo = document.getElementById('select_tipo_tarifa');

    // Configuración reutilizable para notificaciones elegantes
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    // 1. CARGA AUTOMÁTICA AL CAMBIAR TIPO
    if (selectTipo) {
        selectTipo.addEventListener('change', async function() {
            const idTipo = this.value;
            if (!idTipo) return;

            console.log("Cargando tarifas para tipo:", idTipo);

            const fd = new FormData();
            fd.append("accion", "obtener_tarifas");
            fd.append("id_tipo", idTipo);

            try {
                const res = await fetch(URL_AJAX, {
                    method: 'POST',
                    body: fd
                });
                const r = await res.json();

                if (r.ok) {
                    // Poner todos a 0 primero
                    for (let i = 1; i <= 5; i++) {
                        const input = document.getElementById('input_tarifa_' + i);
                        if (input) input.value = "0.00";
                    }

                    // Rellenar con los datos recibidos
                    r.data.forEach(t => {
                        const input = document.getElementById('input_tarifa_' + t.cantidad_personas);
                        if (input) input.value = parseFloat(t.precio_noche).toFixed(2);
                    });
                } else {
                    console.error("Error del servidor:", r.mensaje);
                }
            } catch (error) {
                console.error("Error en la petición AJAX:", error);
            }
        });
    }

    // 2. GUARDAR TARIFAS
    const formTarifas = document.getElementById('formGuardarTarifas');
    if (formTarifas) {
        formTarifas.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);

            try {
                const res = await fetch(URL_AJAX, {
                    method: 'POST',
                    body: fd
                });
                const r = await res.json();
                
                if (r.ok) {
                    // Notificación elegante de éxito
                    Toast.fire({
                        icon: 'success',
                        title: r.mensaje
                    });
                    
                    // Cerrar el modal de Bootstrap
                    const modalElement = document.getElementById('modalTarifas');
                    const modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                } else {
                    // Notificación de error
                    Toast.fire({
                        icon: 'error',
                        title: 'Error',
                        text: r.mensaje
                    });
                }
            } catch (err) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error de conexión'
                });
            }
        });
    }
});
</script>