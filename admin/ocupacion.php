<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";
include "includes/cabecera_admin.php";

$fecha_consulta = $_GET['fecha_filtro'] ?? date('Y-m-d');
$error_reserva = $_GET['error'] ?? '';
$ok_reserva = isset($_GET['success']);

$tarifas_db = $conexion->query("SELECT cantidad_personas, precio_noche FROM tarifa")->fetchAll(PDO::FETCH_ASSOC);
$tarifas_js = [];
foreach ($tarifas_db as $t) {
    $tarifas_js[(int)$t['cantidad_personas']] = (float)$t['precio_noche'];
}

$query = "SELECT h.id_habitacion, h.numero, h.id_tipo, th.nombre as tipo_nombre, h.precio_noche,
          (SELECT COUNT(*) FROM reserva_habitacion rh
           JOIN reserva r ON rh.id_reserva = r.id_reserva
           WHERE rh.id_habitacion = h.id_habitacion
             AND r.estado = 'CONFIRMADA'
             AND :fecha BETWEEN r.fecha_entrada AND r.fecha_salida) as esta_ocupada
          FROM habitacion h
          LEFT JOIN tipo_habitacion th ON h.id_tipo = th.id_tipo
          ORDER BY CAST(h.numero AS UNSIGNED) ASC";

$stmt = $conexion->prepare($query);
$stmt->execute([':fecha' => $fecha_consulta]);
$habitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .habitacion-card { transition: transform 0.2s; border: none; }
    .habitacion-card:hover { transform: translateY(-5px); }
    .status-badge { font-size: 0.75rem; letter-spacing: 1px; }
    .bg-ocupado { background-color: #dc3545 !important; }
    .bg-disponible { background-color: #198754 !important; }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($error_reserva === 'no_disponible'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon: 'warning', title: 'Habitacion no disponible', text: 'La habitacion ya esta ocupada en ese rango de fechas.' });
});
</script>
<?php elseif ($error_reserva === 'fechas_invalidas'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon: 'error', title: 'Fechas invalidas', text: 'Revise las fechas de entrada y salida.' });
});
</script>
<?php elseif ($ok_reserva): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon: 'success', title: 'Reserva registrada', text: 'La reserva se guardo correctamente.' });
});
</script>
<?php endif; ?>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body bg-light rounded">
        <form method="GET" class="row align-items-center g-3">
            <div class="col-md-3">
                <h2 class="fw-bold mb-0"><i class="bi bi-calendar3"></i> Ocupación</h2>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white">Consultar Fecha:</span>
                    <input type="date" name="fecha_filtro" id="input-fecha" class="form-control"
                           value="<?php echo htmlspecialchars($fecha_consulta); ?>" onchange="this.form.submit()">
                    <a href="ocupacion.php" class="btn btn-success fw-bold border-start">Hoy</a>
                </div>
            </div>
            <div class="col-md-3 text-end">
                <span class="badge bg-dark p-2 fs-6"><?php echo date('d/m/Y', strtotime($fecha_consulta)); ?></span>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($habitaciones as $hab):
        $ocupada = ((int)$hab['esta_ocupada'] > 0);
        $clase_bg = $ocupada ? 'bg-ocupado' : 'bg-disponible';
        $texto_estado = $ocupada ? 'OCUPADA' : 'DISPONIBLE';

        $sql_p = "SELECT hp.cantidad, tp.nombre as cama_tipo, tp.plazas
                  FROM habitacion_plaza hp
                  JOIN tipo_plaza tp ON hp.id_tipo_plaza = tp.id_tipo_plaza
                  WHERE hp.id_habitacion = ?";
        $stmt_p = $conexion->prepare($sql_p);
        $stmt_p->execute([(int)$hab['id_habitacion']]);
        $detalles_camas = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

        $capacidad = 0;
        foreach ($detalles_camas as $d) {
            $capacidad += ((int)$d['cantidad'] * (int)$d['plazas']);
        }
    ?>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card habitacion-card shadow-sm h-100">
            <div class="card-header <?php echo $clase_bg; ?> text-white text-center py-1">
                <span class="status-badge fw-bold"><?php echo $texto_estado; ?></span>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h4 class="fw-bold mb-0"># <?php echo (int)$hab['numero']; ?></h4>
                        <small class="text-muted text-uppercase"><?php echo htmlspecialchars($hab['tipo_nombre'] ?? ''); ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark border"><?php echo (int)$capacidad; ?> plazas</span>
                    </div>
                </div>

                <div class="my-3">
                    <?php foreach ($detalles_camas as $d): ?>
                        <div class="d-flex justify-content-between small text-muted border-bottom py-1">
                            <span><?php echo htmlspecialchars($d['cama_tipo']); ?></span>
                            <span>x<?php echo (int)$d['cantidad']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="fw-bold text-primary"><?php echo number_format((float)$hab['precio_noche'], 2); ?>&euro;</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-success text-white" type="button"
                                onclick="verDetalles(<?php echo (int)$hab['id_habitacion']; ?>, '<?php echo (int)$hab['numero']; ?>')">
                            Ver Calendario
                        </button>
                        <button class="btn btn-sm btn-primary" type="button"
                                onclick="abrirReserva(<?php echo (int)$hab['id_habitacion']; ?>, '<?php echo (int)$hab['numero']; ?>', <?php echo (int)$capacidad; ?>)">
                            Reservar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalCalendario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Disponibilidad Habitación <span id="num-hab-modal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReserva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form id="formReservaAdmin" action="acciones_reserva.php" method="POST" class="modal-content shadow-lg border-0" autocomplete="off">
            <input type="hidden" name="accion" value="crear_reserva_completa">
            <input type="hidden" name="origen" value="ocupacion">
            <input type="hidden" name="id_habitacion" id="res_habitacion_id">
            <input type="hidden" name="total" id="res_total_val" value="0">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Registrar Reserva - Habitación <span id="res_num_habitacion"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Datos del Cliente</div>
                            <div class="card-body">
                                <div class="row mb-3 pb-3 border-bottom">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-primary">Cliente antiguo (DNI o Apellido)</label>
                                        <div class="input-group">
                                            <input type="text" id="buscar_cliente_admin" class="form-control" placeholder="Buscar cliente..." autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                            <button class="btn btn-outline-secondary" type="button" id="btn_limpiar_cliente_admin">Limpiar</button>
                                        </div>
                                        <div id="resultados_cliente_admin" class="list-group mt-1" style="display:none; position:absolute; z-index:1000; width:94%;"></div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label small fw-bold">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                                    <div class="col-12"><label class="form-label small fw-bold">DNI / Documento</label><input type="text" name="documento" class="form-control" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Teléfono</label><input type="text" name="telefono" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Detalles de Estancia</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label small fw-bold">Fecha Entrada</label><input type="date" name="fecha_entrada" id="res_fecha_entrada" class="form-control" min="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Fecha Salida</label><input type="date" name="fecha_salida" id="res_fecha_salida" class="form-control" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Número de Huéspedes</label><input type="number" name="num_huespedes" id="res_huespedes" class="form-control" min="1" value="1" required><small class="text-muted">Capacidad máxima: <span id="res_capacidad_txt">0</span></small></div>
                                    <div class="col-md-6"><label class="form-label small fw-bold">Método de Pago</label><select name="metodo_pago" class="form-select"><option value="EFECTIVO">Efectivo</option><option value="TARJETA">Tarjeta</option><option value="TRANSFERENCIA">Transferencia</option></select></div>
                                    <div class="col-12"><div class="p-3 rounded bg-dark text-white"><span class="d-block small text-secondary">Total Estancia</span><h3 class="mb-0 text-success fw-bold">&euro; <span id="res_total_txt">0.00</span></h3></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-5 fw-bold">Confirmar y Registrar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
let calendar;
const TARIFAS_ADMIN = <?php echo json_encode($tarifas_js); ?>;
let capacidadHabitacionActual = 0;

function verDetalles(id, numero) {
    const modal = new bootstrap.Modal(document.getElementById('modalCalendario'));
    document.getElementById('num-hab-modal').textContent = "#" + numero;
    modal.show();
    setTimeout(() => {
        if (calendar) calendar.destroy();
        calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth',
            locale: 'es',
            events: '../get_disponibilidad.php?id=' + id
        });
        calendar.render();
    }, 200);
}

function abrirReserva(idHabitacion, numeroHabitacion, capacidadHabitacion) {
    document.getElementById('res_habitacion_id').value = idHabitacion;
    document.getElementById('res_num_habitacion').textContent = "#" + numeroHabitacion;
    document.getElementById('res_capacidad_txt').textContent = capacidadHabitacion;
    document.getElementById('res_huespedes').max = capacidadHabitacion > 0 ? capacidadHabitacion : 10;
    capacidadHabitacionActual = capacidadHabitacion;

    const fechaFiltro = document.getElementById('input-fecha') ? document.getElementById('input-fecha').value : '';
    if (fechaFiltro) {
        document.getElementById('res_fecha_entrada').value = fechaFiltro;
        document.getElementById('res_fecha_salida').value = fechaFiltro;
    }

    calcularTotalReservaAdmin();
    new bootstrap.Modal(document.getElementById('modalReserva')).show();
}

function calcularTotalReservaAdmin() {
    const fEntrada = document.getElementById('res_fecha_entrada').value;
    const fSalida = document.getElementById('res_fecha_salida').value;
    const huespedes = parseInt(document.getElementById('res_huespedes').value || '0', 10);
    let total = 0;

    if (fEntrada && fSalida && huespedes > 0) {
        const d1 = new Date(fEntrada + "T00:00:00");
        const d2 = new Date(fSalida + "T00:00:00");
        const noches = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
        if (noches > 0) {
            let tarifa = TARIFAS_ADMIN[huespedes];
            if (tarifa === undefined) {
                const keys = Object.keys(TARIFAS_ADMIN).map(Number);
                const maxKey = keys.length ? Math.max(...keys) : 1;
                tarifa = TARIFAS_ADMIN[maxKey] || 0;
            }
            total = noches * parseFloat(tarifa || 0);
        }
    }

    document.getElementById('res_total_txt').textContent = total.toFixed(2);
    document.getElementById('res_total_val').value = total.toFixed(2);
}

document.getElementById('res_fecha_entrada').addEventListener('change', calcularTotalReservaAdmin);
document.getElementById('res_fecha_salida').addEventListener('change', calcularTotalReservaAdmin);
document.getElementById('res_huespedes').addEventListener('input', function() {
    const v = parseInt(this.value || '0', 10);
    if (capacidadHabitacionActual > 0 && v > capacidadHabitacionActual) this.value = capacidadHabitacionActual;
    if (v <= 0) this.value = 1;
    calcularTotalReservaAdmin();
});

document.getElementById('formReservaAdmin').addEventListener('submit', function(e) {
    const fEntrada = document.getElementById('res_fecha_entrada').value;
    const fSalida = document.getElementById('res_fecha_salida').value;
    const huespedes = parseInt(document.getElementById('res_huespedes').value || '0', 10);
    const total = parseFloat(document.getElementById('res_total_val').value || '0');

    if (!fEntrada || !fSalida || fSalida <= fEntrada) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Rango de fechas invalido', text: 'La fecha de salida debe ser posterior a la de entrada.' });
        return;
    }
    if (capacidadHabitacionActual > 0 && huespedes > capacidadHabitacionActual) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Capacidad excedida', text: 'La cantidad de huespedes supera la capacidad de la habitacion.' });
        return;
    }
    if (total <= 0) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Total invalido', text: 'Revise las fechas para calcular un total valido.' });
    }
});

const buscarInput = document.getElementById('buscar_cliente_admin');
const listaResultados = document.getElementById('resultados_cliente_admin');
buscarInput.setAttribute('autocomplete', 'new-password');

buscarInput.addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 3) {
        listaResultados.style.display = 'none';
        return;
    }
    fetch(`buscar_cliente_ajax.php?q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
            listaResultados.innerHTML = '';
            if (!data || data.length === 0) {
                listaResultados.style.display = 'none';
                return;
            }
            data.forEach(p => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<strong>${p.documento || ''}</strong> - ${p.nombre || ''} ${p.apellidos || ''}`;
                item.onclick = function(ev) {
                    ev.preventDefault();
                    document.querySelector('#formReservaAdmin input[name="documento"]').value = p.documento || '';
                    document.querySelector('#formReservaAdmin input[name="nombre"]').value = p.nombre || '';
                    document.querySelector('#formReservaAdmin input[name="apellidos"]').value = p.apellidos || '';
                    document.querySelector('#formReservaAdmin input[name="email"]').value = p.email || '';
                    document.querySelector('#formReservaAdmin input[name="telefono"]').value = p.telefono || '';
                    buscarInput.value = `${p.nombre || ''} ${p.apellidos || ''}`.trim();
                    listaResultados.style.display = 'none';
                };
                listaResultados.appendChild(item);
            });
            listaResultados.style.display = 'block';
        })
        .catch(() => { listaResultados.style.display = 'none'; });
});

document.getElementById('btn_limpiar_cliente_admin').addEventListener('click', function() {
    buscarInput.value = '';
    listaResultados.style.display = 'none';
});
</script>

<?php include "includes/pie_admin.php"; ?>
