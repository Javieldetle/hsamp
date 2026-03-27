
<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";

// --- LÓGICA AJAX ---
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === "actualizar_precios_habitacion") {
            $id_tipo = $_POST['id_tipo']; 
            $stmt = $conexion->prepare("UPDATE tarifa SET precio_noche = ? WHERE id_tipo = ? AND cantidad_personas = ?");
            for ($i = 1; $i <= 5; $i++) {
                $precio = $_POST["p$i"] ?? 0;
                $stmt->execute([$precio, $id_tipo, $i]);
            }
            echo json_encode(["ok" => true, "mensaje" => "Tarifas actualizadas correctamente."]);
        } elseif ($accion === "guardar_extra") {
            if (!empty($_POST['id_extra'])) {
                $sql = "UPDATE extra SET nombre = ?, precio = ? WHERE id_extra = ?";
                $conexion->prepare($sql)->execute([$_POST['nombre'], $_POST['precio'], $_POST['id_extra']]);
            } else {
                $sql = "INSERT INTO extra (nombre, precio, activo) VALUES (?, ?, 1)";
                $conexion->prepare($sql)->execute([$_POST['nombre'], $_POST['precio']]);
            }
            echo json_encode(["ok" => true, "mensaje" => "Servicio extra guardado."]);
        } elseif ($accion === "eliminar_extra") {
            $sql = "UPDATE extra SET activo = 0 WHERE id_extra = ?";
            $conexion->prepare($sql)->execute([$_POST['id_extra']]);
            echo json_encode(["ok" => true, "mensaje" => "Servicio eliminado."]);
        }
    } catch (Exception $e) { 
        echo json_encode(["ok" => false, "mensaje" => $e->getMessage()]); 
    }
    exit;
}

include "includes/cabecera_admin.php";

// --- CONSULTAS ---
$tarifas_db = $conexion->query("SELECT id_tipo, cantidad_personas, precio_noche FROM tarifa")->fetchAll();
$precios_map = [];
foreach ($tarifas_db as $t) {
    $precios_map[$t['id_tipo']][$t['cantidad_personas']] = $t['precio_noche'];
}

$sqlHab = "SELECT h.*, t.nombre as categoria FROM habitacion h 
           JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo ORDER BY h.numero ASC";
$habitaciones = $conexion->query($sqlHab)->fetchAll();
$extras = $conexion->query("SELECT * FROM extra WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid mt-4 px-4">
    <h2 class="fw-bold mb-4">Gestión de Tarifas y Servicios</h2>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0 card-custom">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-bed me-2"></i>Configuración de Precios por Habitación</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>Hab</th>
                                    <th>Categoría</th>
                                    <th>1 Pers.</th>
                                    <th>2 Pers.</th>
                                    <th>3 Pers.</th>
                                    <th>4 Pers.</th>
                                    <th>5 Pers.</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($habitaciones as $h): ?>
                                <tr class="text-center">
                                    <td><span class="badge bg-primary fs-6">#<?= $h['numero'] ?></span></td>
                                    <td class="text-start fw-bold"><?= $h['categoria'] ?></td>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <td>
                                        <input type="number" step="0.01" 
                                               class="form-control form-control-sm text-end input-precio" 
                                               id="p<?= $i ?>_<?= $h['id_habitacion'] ?>" 
                                               value="<?= $precios_map[$h['id_tipo']][$i] ?? '0.00' ?>">
                                    </td>
                                    <?php endfor; ?>
                                    <td>
                                        <button class="btn btn-primary btn-sm btn-save-h fw-bold" 
                                                data-id="<?= $h['id_habitacion'] ?>" 
                                                data-id-tipo="<?= $h['id_tipo'] ?>">
                                            Guardar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm border-0 card-custom">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-concierge-bell me-2"></i>Servicios Extras</h5>
                    <button class="btn btn-light btn-sm fw-bold shadow-sm" onclick="nuevoExtra()">
                        <i class="fas fa-plus me-1"></i> Nuevo Servicio
                    </button>
                </div>
                <div class="card-body bg-light p-4">
                    <div class="row g-4"> 
                        <?php foreach ($extras as $ex): ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card h-100 border-0 shadow-sm extra-card">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="fw-bold mb-0 text-dark"><?= strtoupper($ex['nombre']) ?></h6>
                                        <span class="badge bg-soft-success text-success fw-bold fs-6">
                                            <?= number_format($ex['precio'], 2) ?>€
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm flex-grow-1 fw-bold btn-edit-extra" 
                                         style="width: 2px;"
                                                data-id="<?= $ex['id_extra'] ?>" 
                                                data-nombre="<?= $ex['nombre'] ?>" 
                                                data-precio="<?= $ex['precio'] ?>">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm fw-bold" 
                                                style="width: 50%;"
                                                onclick="eliminarExtra(<?= $ex['id_extra'] ?>, '<?= $ex['nombre'] ?>')">
                                            <i class="fas fa-trash-alt"></i> Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExtra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formExtra" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="tituloModalExtra">Detalles del Servicio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_extra" id="ex_id">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre del Servicio</label>
                    <input type="text" name="nombre" id="ex_nom" class="form-control" placeholder="Ej: Desayuno" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Precio (€)</label>
                    <input type="number" step="0.01" name="precio" id="ex_pre" class="form-control" placeholder="0.00" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-bold">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/precios_accion.js"></script>

<?php include "includes/pie_admin.php"; ?>