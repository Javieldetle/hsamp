<?php
require_once "app/includes/seguridad_recep.php";
require_once "app/config/conexion.php";
include "app/includes/cabecera_recep.php";

// 1) Obtener Tarifas para el cálculo en JS
$tarifas_db = $conexion->query("SELECT cantidad_personas, precio_noche FROM tarifa")->fetchAll(PDO::FETCH_ASSOC);
$tarifas_js = [];
foreach ($tarifas_db as $t) {
    $tarifas_js[$t['cantidad_personas']] = $t['precio_noche'];
}

// 2) Obtener Reservas Activas
$sql = "SELECT r.*, c.id_persona, per.nombre, per.apellidos, h.id_habitacion, h.numero AS num_hab, th.nombre AS tipo_hab
        FROM reserva r
        JOIN cliente c ON r.id_cliente = c.id_cliente
        JOIN persona per ON c.id_persona = per.id_persona
        JOIN reserva_habitacion rh ON r.id_reserva = rh.id_reserva
        JOIN habitacion h ON rh.id_habitacion = h.id_habitacion
        JOIN tipo_habitacion th ON h.id_tipo = th.id_tipo
        WHERE r.fecha_salida >= CURDATE() 
        AND r.estado != 'CANCELADA' 
        ORDER BY r.fecha_entrada ASC";
$reservas = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
// 3) Obtener habitaciones
$habitaciones = $conexion->query("SELECT h.id_habitacion, h.numero, th.nombre FROM habitacion h JOIN tipo_habitacion th ON h.id_tipo = th.id_tipo")->fetchAll(PDO::FETCH_ASSOC);
$error_reserva = $_GET['error'] ?? '';
?>

<style>
    .modal-header { cursor: move; user-select: none; }
    .bg-light-custom { background-color: #f8f9fa; border: 1px solid #dee2e6; }
    /* Evita que el modal se comporte extraño al arrastrar */
    #modalEditar .modal-content { transition: none !important; }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($error_reserva === 'no_disponible'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'warning',
        title: 'Habitaci\u00f3n no disponible',
        text: 'La habitaci\u00f3n ya est\u00e1 ocupada en ese rango de fechas. Seleccione otra fecha u otra habitaci\u00f3n.',
        confirmButtonText: 'Entendido'
    });
});
</script>
<?php elseif ($error_reserva === 'fechas_invalidas'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Fechas inv\u00e1lidas',
        text: 'Revise las fechas de entrada y salida para registrar la reserva.',
        confirmButtonText: 'Entendido'
    });
});
</script>
<?php endif; ?>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Gestión de Reservas</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva">Nueva Reserva</button>
    </div>

    <div class="card shadow-sm border-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Cliente</th>
                    <th>ID Persona</th>
                    <th>Habitación</th>
                    <th>Fechas</th>
                    <th class="text-center">Huéspedes</th> 
                    <th class="text-end">Costo Total</th> 
                    <th>Pago</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reservas as $res): 
    // Calculamos cuánto se ha pagado en total para esta reserva
    $stmtPago = $conexion->prepare("SELECT SUM(importe) FROM pago WHERE id_reserva = ?");
    $stmtPago->execute([$res['id_reserva']]);
    $pagado_ya = $stmtPago->fetchColumn() ?: 0;
    
    // La diferencia entre el costo total y lo abonado
    $diferencia = $res['total'] - $pagado_ya;
?>
<tr>
    <td><?php echo $res['nombre']." ".$res['apellidos']; ?></td>
    <td><span class="badge bg-secondary"><?php echo (int)$res['id_persona']; ?></span></td>
    <td><span class="badge bg-info text-dark">Hab. <?php echo $res['num_hab']; ?></span></td>
    <td><?php echo date("d/m/Y", strtotime($res['fecha_entrada'])); ?> al <?php echo date("d/m/Y", strtotime($res['fecha_salida'])); ?></td>
    <td class="text-center"><?php echo $res['num_huespedes']; ?></td>
    <td class="text-end fw-bold">$<?php echo number_format($res['total'], 2, ',', '.'); ?></td>

    <td class="align-middle">
        <div class="d-flex align-items-center gap-3">
        <?php if ($diferencia <= 0) : ?>
            <span class="badge bg-success" style="font-size: 0.9rem;">Pagado</span>
        <?php else : ?>
            <span class="badge bg-danger" style="font-size: 0.9rem;">Pendiente</span>
            <br>
            <a href="../procesar_pago.php?id_reserva=<?php echo $res['id_reserva']; ?>&total=<?php echo $diferencia; ?>&from=recep_reservas" 
               class="btn btn-xs btn-warning mt-1" 
               style="font-size: 0.95rem; padding: 2px 5px; font-weight: bold;">
               Pagar Diferencia ($<?php echo number_format($diferencia, 2); ?>)
            </a>
        <?php endif; ?>
        </div>
    </td>

    <td class="text-center">
        <button class="btn btn-sm btn-warning" onclick='abrirEditar(<?php echo json_encode($res); ?>)'>Editar</button>
        <button class="btn btn-sm btn-danger" onclick="confirmarBorrado(<?php echo $res['id_reserva']; ?>)">Eliminar</button>
    </td>
</tr>

<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="acciones_reserva.php" method="POST" class="modal-content">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_reserva" id="edit_id">
            <input type="hidden" name="total" id="edit_total_val"> 

            <div class="modal-header bg-primary text-white" id="modalHeader">
                <h5 class="modal-title">Editar Reserva #<span id="label_id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Habitación</label>
                        <select name="id_habitacion" id="edit_hab" class="form-select">
                            <?php foreach($habitaciones as $h): ?>
                                <option value="<?php echo $h['id_habitacion']; ?>">Hab. <?php echo $h['numero']; ?> - <?php echo $h['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Entrada</label>
                        <input type="date" name="fecha_entrada" id="edit_entrada" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Salida</label>
                        <input type="date" name="fecha_salida" id="edit_salida" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Huéspedes</label>
                        <input type="number" name="num_huespedes" id="edit_huespedes" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Estado</label>
                        <select name="estado" id="edit_estado" class="form-select">
                            <option value="PENDIENTE">PENDIENTE</option>
                            <option value="CONFIRMADA">CONFIRMADA</option>
                            <option value="CANCELADA">CANCELADA</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4 p-3 bg-light-custom rounded shadow-sm">
                        <div class="row text-center align-items-center">
                            <div class="col-4 border-end">
                                <small class="text-muted d-block">Tarifa/Noche</small>
                                <span class="fw-bold h5" id="resumen_tarifa">$0.00</span>
                            </div>
                            <div class="col-4 border-end">
                                <small class="text-muted d-block">Noches</small>
                                <span class="fw-bold h5" id="resumen_noches">0</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Total a Pagar</small>
                                <span class="h4 mb-0 fw-bold text-primary">$<span id="resumen_total">0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary px-4">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalNueva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <form action="acciones_reserva.php" method="POST" class="modal-content shadow-lg border-0" autocomplete="off">
            <input type="hidden" name="accion" value="crear_reserva_completa">
            <input type="hidden" name="origen" value="reservas">
            
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Registrar Nueva Reserva</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="row mb-3 pb-3 border-bottom">
    <div class="col-md-12">
        <label class="form-label small fw-bold text-primary">¿Cliente antiguo? Buscar por DNI o Apellido</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="buscar_cliente_input" class="form-control" placeholder="Escriba DNI o Apellidos para buscar..." autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
            <button class="btn btn-outline-secondary" type="button" id="btn_limpiar_busqueda">Limpiar</button>
        </div>
        <div id="resultados_busqueda" class="list-group mt-1" style="display:none; position:absolute; z-index:1000; width:94%;"></div>
    </div>
</div>
                            <div class="card-header bg-white fw-bold"><i class="bi bi-person-fill"></i> Datos del Cliente</div>
                            <div class="card-body">
                                <input type="hidden" name="id_persona_existente" id="id_persona_existente" value="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Apellidos</label>
                                        <input type="text" name="apellidos" class="form-control" placeholder="Ej: Pérez" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">DNI / Documento</label>
                                        <input type="text" name="documento" class="form-control" placeholder="Número de identidad" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Teléfono</label>
                                        <input type="tel" name="telefono" class="form-control" placeholder="+34 000 000 000">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Email (Opcional)</label>
                                        <input type="email" name="email" class="form-control" placeholder="cliente@correo.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold"><i class="bi bi-door-open-fill"></i> Detalles de Estancia</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Habitación Disponible</label>
                                        <select name="id_habitacion" id="new_hab" class="form-select select-calculo" required>
                                            <option value="">Seleccione habitación...</option>
                                            <?php foreach($habitaciones as $h): ?>
                                                <option value="<?= $h['id_habitacion']; ?>">Hab. <?= $h['numero']; ?> - <?= $h['nombre']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Fecha Entrada</label>
                                        <input type="date" name="fecha_entrada" id="new_entrada" class="form-control select-calculo" min="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Fecha Salida</label>
                                        <input type="date" name="fecha_salida" id="new_salida" class="form-control select-calculo" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Número de Huéspedes</label>
                                        <input type="number" name="num_huespedes" id="new_huespedes" class="form-control select-calculo" min="1" max="5" value="1" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Método de Pago</label>
                                        <select name="metodo_pago" class="form-select">
                                            <option value="EFECTIVO">Efectivo</option>
                                            <option value="TARJETA">Tarjeta</option>
                                            <option value="TRANSFERENCIA">Transferencia</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 mt-3">
                                        <div class="p-3 rounded bg-dark text-white d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="d-block small text-secondary">Total Estancia</span>
                                                <h3 class="mb-0 text-success fw-bold">$ <span id="new_total_display">0.00</span></h3>
                                                <input type="hidden" name="total" id="new_total_val">
                                            </div>
                                            <div class="text-end">
                                                <div class="form-check form-switch fs-5">
                                                    <input class="form-check-input" type="checkbox" name="marcar_pagado" id="pagoCheck" checked>
                                                    <label class="form-check-label small" for="pagoCheck">¿Cobrar ahora?</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 1) Datos de tarifas desde PHP
const TARIFAS = <?php echo json_encode($tarifas_js); ?>;

function calcularPrecio() {
    const f_entrada = document.getElementById('edit_entrada').value;
    const f_salida = document.getElementById('edit_salida').value;
    const inputHuespedes = document.getElementById('edit_huespedes');
    const cant_huespedes = parseInt(inputHuespedes.value);

    // Verificamos que tengamos fechas y huéspedes válidos
    if (f_entrada && f_salida && !isNaN(cant_huespedes)) {
        
        const start = new Date(f_entrada + "T00:00:00");
        const end = new Date(f_salida + "T00:00:00");
        
        // Calcular diferencia de días
        const diff = end - start;
        const noches = Math.round(diff / (1000 * 60 * 60 * 24));

        // BUSCAR TARIFA: Intentar obtener la tarifa para esa cantidad de personas
        // Si no existe (ej. pones 10 y solo hay hasta 5), toma la tarifa máxima disponible
        let precioPorNoche = TARIFAS[cant_huespedes];
        
        if (precioPorNoche === undefined) {
            const keys = Object.keys(TARIFAS).map(Number);
            const maxHuespedes = Math.max(...keys);
            precioPorNoche = TARIFAS[maxHuespedes] || 0;
            console.warn("Tarifa no encontrada para " + cant_huespedes + " personas. Usando tarifa máxima.");
        }

        if (noches > 0) {
            const totalFinal = noches * precioPorNoche;
            
            // Actualizar visualmente el Modal
            document.getElementById('resumen_tarifa').innerText = '$' + parseFloat(precioPorNoche).toLocaleString('es-CL');
            document.getElementById('resumen_noches').innerText = noches;
            document.getElementById('resumen_total').innerText = totalFinal.toFixed(2);
            
            // Actualizar el valor oculto para el POST de PHP
            document.getElementById('edit_total_val').value = totalFinal.toFixed(2);
        } else {
            // Caso donde la fecha de salida es igual o menor a la de entrada
            document.getElementById('resumen_noches').innerText = "0";
            document.getElementById('resumen_total').innerText = "0.00";
            document.getElementById('edit_total_val').value = "0.00";
        }
    }
}

// Escuchar cambios en tiempo real
document.getElementById('edit_entrada').addEventListener('change', calcularPrecio);
document.getElementById('edit_salida').addEventListener('change', calcularPrecio);
document.getElementById('edit_huespedes').addEventListener('input', calcularPrecio);
// También al cambiar la habitación por si afectara a futuro
document.getElementById('edit_hab').addEventListener('change', calcularPrecio);

function abrirEditar(d) {
    // Cargar datos
    document.getElementById('edit_id').value = d.id_reserva;
    document.getElementById('label_id').innerText = d.id_reserva;
    document.getElementById('edit_hab').value = d.id_habitacion;
    document.getElementById('edit_entrada').value = d.fecha_entrada;
    document.getElementById('edit_salida').value = d.fecha_salida;
    document.getElementById('edit_huespedes').value = d.num_huespedes;
    document.getElementById('edit_estado').value = d.estado;
    
    // Limpiar arrastre previo
    const content = document.querySelector('#modalEditar .modal-content');
    content.style.transform = 'translate(0px, 0px)';
    xOffset = 0; yOffset = 0;

    // Ejecutar cálculo con un ligero delay para asegurar que los campos cargaron
    setTimeout(calcularPrecio, 150);
    
    const myModal = new bootstrap.Modal(document.getElementById('modalEditar'));
    myModal.show();
}

function confirmarBorrado(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esta acción!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', // Rojo para eliminar
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar reserva',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Si el usuario confirma, redirigimos a la acción de eliminar
            window.location.href = 'acciones_reserva.php?accion=eliminar&id=' + id;
        }
    });
}

// Función genérica para calcular precios
function calcularPrecioNuevo() {
    const f_entrada = document.getElementById('new_entrada').value;
    const f_salida = document.getElementById('new_salida').value;
    const n_huespedes = parseInt(document.getElementById('new_huespedes').value);

    if (f_entrada && f_salida && n_huespedes) {
        const start = new Date(f_entrada + "T00:00:00");
        const end = new Date(f_salida + "T00:00:00");
        const noches = Math.round((end - start) / (1000 * 60 * 60 * 24));

        if (noches > 0) {
            // Obtener tarifa de la tabla (usando el objeto TARIFAS inyectado desde PHP)
            let precioNoche = TARIFAS[n_huespedes] || 0;
            const total = noches * precioNoche;

            // Actualizar vista
            document.getElementById('new_total_display').innerText = total.toFixed(2);
            // Actualizar input oculto para el envío POST
            document.getElementById('new_total_val').value = total.toFixed(2);
        } else {
            document.getElementById('new_total_display').innerText = "0.00";
            document.getElementById('new_total_val').value = "0";
        }
    }
}

// Asignar eventos a los campos del modal NUEVO
document.getElementById('new_entrada').addEventListener('change', calcularPrecioNuevo);
document.getElementById('new_salida').addEventListener('change', calcularPrecioNuevo);
document.getElementById('new_huespedes').addEventListener('input', calcularPrecioNuevo);

// Lógica de arrastre
const modalContent = document.querySelector('#modalEditar .modal-content');
const modalHeader = document.getElementById('modalHeader');
let isDragging = false, currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;

modalHeader.addEventListener('mousedown', (e) => {
    initialX = e.clientX - xOffset;
    initialY = e.clientY - yOffset;
    if (e.target === modalHeader || modalHeader.contains(e.target)) isDragging = true;
});

document.addEventListener('mousemove', (e) => {
    if (isDragging) {
        e.preventDefault();
        currentX = e.clientX - initialX;
        currentY = e.clientY - initialY;
        xOffset = currentX;
        yOffset = currentY;
        modalContent.style.transform = `translate(${currentX}px, ${currentY}px)`;
    }
});

document.addEventListener('mouseup', () => isDragging = false);
</script>
<script>
    const inputBuscar = document.getElementById('buscar_cliente_input');
const listaResultados = document.getElementById('resultados_busqueda');
inputBuscar.setAttribute('autocomplete', 'new-password');

inputBuscar.addEventListener('input', function() {
    const q = this.value;
    if (q.length >= 3) {
        fetch(`buscar_cliente_ajax.php?q=${q}`)
            .then(res => res.json())
            .then(data => {
                listaResultados.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${p.documento}</strong> - ${p.nombre} ${p.apellidos}`;
                        item.onclick = (e) => {
                            e.preventDefault();
                            seleccionarCliente(p);
                        };
                        listaResultados.appendChild(item);
                    });
                    listaResultados.style.display = 'block';
                } else {
                    listaResultados.style.display = 'none';
                }
            });
    } else {
        listaResultados.style.display = 'none';
    }
});

function seleccionarCliente(p) {
    // Rellenamos los campos del modal con los datos encontrados
    document.getElementById('id_persona_existente').value = p.id_persona || '';
    document.querySelector('input[name="documento"]').value = p.documento;
    document.querySelector('input[name="nombre"]').value = p.nombre;
    document.querySelector('input[name="apellidos"]').value = p.apellidos;
    document.querySelector('input[name="email"]').value = p.email;
    document.querySelector('input[name="telefono"]').value = p.telefono;
    
    // Ocultamos la búsqueda y avisamos al usuario
    listaResultados.style.display = 'none';
    inputBuscar.value = p.nombre + " " + p.apellidos;
    const aviso = document.getElementById('aviso_cliente_cargado'); // Si creas un <span> previo
if(aviso) {
    aviso.textContent = "✓ Cliente cargado";
    aviso.className = "text-success small ms-2";
}
}

document.getElementById('btn_limpiar_busqueda').onclick = function() {
    inputBuscar.value = '';
    listaResultados.style.display = 'none';
    document.getElementById('id_persona_existente').value = '';
};
</script>
<?php include "app/includes/pie_recep.php"; ?>
