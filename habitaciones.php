<?php
require_once "app/includes/seguridad_cliente.php";
require_once "app/config/conexion.php";
include "app/includes/cabecera.php";

$hoy = date('Y-m-d'); // Obtenemos la fecha actual

// Consulta mejorada: detecta si hoy la habitaciÃ³n estÃ¡ ocupada
$query = "SELECT h.id_habitacion, h.numero, h.id_tipo, th.nombre as tipo_nombre, h.precio_noche,
          (SELECT COUNT(*) FROM reserva_habitacion rh 
           JOIN reserva r ON rh.id_reserva = r.id_reserva 
           WHERE rh.id_habitacion = h.id_habitacion 
           AND r.estado = 'CONFIRMADA' 
           AND '$hoy' BETWEEN r.fecha_entrada AND r.fecha_salida) as esta_ocupada_hoy
          FROM habitacion h
          LEFT JOIN tipo_habitacion th ON h.id_tipo = th.id_tipo
          ORDER BY CAST(h.numero AS UNSIGNED) ASC";

$stmt = $conexion->prepare($query);
$stmt->execute();
$habitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    #calendar {
        background-color: white;
        padding: 10px;
        border-radius: 5px;
        color: black;
        min-height: 400px;
    }

    .fc-col-header-cell-cushion,
    .fc-daygrid-day-number {
        color: #333 !important;
        text-decoration: none !important;
    }

    .habitacion-card {
        transition: transform 0.2s;
        cursor: pointer;
    }

    .habitacion-card:hover {
        transform: scale(1.02);
    }
</style>

<div class="container mt-5">
    <div class="card p-4 shadow-sm border-0 bg-light">
        <h2 class="fw-bold text-center mb-4"><i class="bi bi-building"></i> Plano de Habitaciones</h2>

        <div class="row g-4">
            <?php foreach ($habitaciones as $hab) :
                $ocupada_hoy = ($hab['esta_ocupada_hoy'] > 0);
                $clase_estado = $ocupada_hoy ? 'bg-secondary' : 'bg-success';
                $texto_hoy = $ocupada_hoy ? 'OCUPADA' : 'DISPONIBLE';

                // LÃ³gica de plazas y camas
                $sql_plazas = "SELECT hp.cantidad, tp.nombre as cama_tipo, tp.plazas 
                       FROM habitacion_plaza hp
                       JOIN tipo_plaza tp ON hp.id_tipo_plaza = tp.id_tipo_plaza
                       WHERE hp.id_habitacion = ?";
                $stmt_p = $conexion->prepare($sql_plazas);
                $stmt_p->execute([$hab['id_habitacion']]);
                $detalles_camas = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

                $capacidad_total = 0;
                $resumen_camas = ""; // Para mostrar en el modal
                foreach ($detalles_camas as $d) {
                    $capacidad_total += ($d['cantidad'] * $d['plazas']);
                    $resumen_camas .= "<li>" . $d['cama_tipo'] . " x" . $d['cantidad'] . "</li>";
                }
            ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card <?php echo $clase_estado; ?> text-white shadow-sm h-100 habitacion-card <?php echo $ocupada_hoy ? 'opacity-75' : ''; ?>"
                        data-bs-toggle="modal" data-bs-target="#modalReserva"
                        data-id="<?php echo $hab['id_habitacion']; ?>"
                        data-numero="<?php echo $hab['numero']; ?>"
                        data-precio="<?php echo $hab['precio_noche']; ?>"
                        data-plazas="<?php echo $capacidad_total; ?>"
                        data-camas="<?php echo htmlspecialchars($resumen_camas); ?>">

                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-bold text-uppercase"><?php echo $hab['tipo_nombre']; ?></small>
                                <span class="badge bg-white text-dark">#<?php echo $hab['numero']; ?></span>
                            </div>

                            <div class="mb-2">
                                <span class="badge <?php echo $ocupada_hoy ? 'bg-danger' : 'bg-light text-success'; ?> w-100 py-2">
                                    Hoy: <?php echo $texto_hoy; ?>
                                </span>
                            </div>

                            <div class="mb-3" style="font-size: 0.85rem;">
                                <?php foreach ($detalles_camas as $d): ?>
                                    <div class="d-flex justify-content-between border-bottom border-white border-opacity-10">
                                        <span><?php echo $d['cama_tipo']; ?>:</span>
                                        <span>x<?php echo $d['cantidad']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-auto d-flex justify-content-between align-items-end">
                                <div><i class="bi bi-people-fill"></i> <?php echo $capacidad_total; ?> plazas</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($hab['precio_noche'], 0); ?>&euro;</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="detalle-camas-modal" class="small text-muted mt-2" style="font-size: 0.8rem;"></div>

        <div class="modal fade" id="modalReserva" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Disponibilidad HabitaciÃ³n <span id="modal-num-hab"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="procesar_reserva.php" method="POST">
                        <div class="modal-body p-4">
                            <input type="hidden" name="id_habitacion" id="input-id-hab">
                            <input type="hidden" name="total_estimado" id="input-total-estimado">
                            <div class="row">
                                <div class="col-md-7">
                                    <div id="calendar"></div>
                                </div>
                                <div class="col-md-5 border-start">
                                    <label class="form-label fw-bold">Fecha Entrada</label>
                                    <input type="date" name="fecha_entrada" id="f_entrada" class="form-control mb-3" min="<?php echo date('Y-m-d'); ?>" required>

                                    <label class="form-label fw-bold">Fecha Salida</label>
                                    <input type="date" name="fecha_salida" id="f_salida" class="form-control mb-3" min="<?php echo date('Y-m-d'); ?>" required>

                                    <label class="form-label fw-bold small">HuÃ©spedes</label>
                                    <input type="number" name="num_huespedes" id="input_huespedes" class="form-control" value="1" min="1" required>
                                    <div id="info-aforo" class="mt-1">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> Aforo mÃ¡ximo: <b id="max-plazas-text">--</b> personas.
                                        </small>
                                    </div>
                                    <label class="form-label fw-bold small mt-3">Metodo de Pago</label>
                                    <select name="metodo_pago" id="metodo_pago" class="form-select mb-3" required>
                                        <option value="TARJETA" selected>Tarjeta (Stripe)</option>
                                        <option value="EFECTIVO">Efectivo</option>
                                    </select>

                                    <div class="alert alert-light border small p-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Importe total:</span>
                                            <b id="importe-total">0.00&euro;</b>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Noches:</span>
                                            <b id="total-noches">0</b>
                                        </div>
                                    </div>

                                    <div class="alert alert-info small p-2">
                                        <i class="bi bi-info-circle"></i> Puedes escribir las fechas o arrastrar en el calendario para seleccionar el rango.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">PAGAR Y CONFIRMAR</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let calendar;
                const modalElement = document.getElementById('modalReserva');
                const inputEntrada = document.getElementById('f_entrada');
                const inputSalida = document.getElementById('f_salida');
                const inputHuespedes = document.getElementById('input_huespedes');
                const btnPagar = document.querySelector('#modalReserva button[type="submit"]');
                const maxPlazasText = document.getElementById('max-plazas-text');
                const detalleCamasModal = document.getElementById('detalle-camas-modal');
                const metodoPago = document.getElementById('metodo_pago');
                const inputTotalEstimado = document.getElementById('input-total-estimado');
                const totalNochesEl = document.getElementById('total-noches');
                const importeTotalEl = document.getElementById('importe-total');
                let precioNocheActual = 0;

                function formatearFechaLocal(fecha) {
                    const y = fecha.getFullYear();
                    const m = String(fecha.getMonth() + 1).padStart(2, '0');
                    const d = String(fecha.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                }

                modalElement.addEventListener('shown.bs.modal', function(event) {
                    const trigger = event.relatedTarget;

                    // Obtenemos los datos de la tarjeta
                    const plazas = parseInt(trigger.getAttribute('data-plazas'));
                    const idHab = trigger.getAttribute('data-id');
                    const numHab = trigger.getAttribute('data-numero');
                    const htmlCamas = trigger.getAttribute('data-camas');
                    precioNocheActual = parseFloat(trigger.getAttribute('data-precio')) || 0;

                    document.getElementById('modal-num-hab').textContent = "#" + numHab;
                    document.getElementById('input-id-hab').value = idHab;

                    // 1. Configurar lÃ­mites y textos
                    maxPlazasText.textContent = plazas;
                    inputHuespedes.max = plazas;
                    inputHuespedes.value = 1; // Inicia en 1 siempre

                    if (detalleCamasModal) {
                        detalleCamasModal.innerHTML = `<p class='mb-0'>DistribuciÃ³n:</p><ul class='mb-0'>${htmlCamas}</ul>`;
                    }

                    // 2. Validar inmediatamente para que no aparezca en rojo al abrir
                    validarAforo(1, plazas);
                    actualizarResumenPago();
                    actualizarTextoBotonPago();

                    // Calendario...
                    if (calendar) calendar.destroy();
                    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                        initialView: 'dayGridMonth',
                        locale: 'es',
                        selectable: true,
                        events: 'get_disponibilidad.php?id=' + idHab,
                        select: function(info) {
                            inputEntrada.value = info.startStr;
                            let endDate = new Date(info.end);
                            endDate.setDate(endDate.getDate() - 1);
                            inputSalida.value = formatearFechaLocal(endDate);
                            actualizarResumenPago();
                        }
                    });
                    calendar.render();
                });

                // Escuchar cuando el usuario cambia el nÃºmero de huÃ©spedes
                inputHuespedes.addEventListener('input', function() {
                    const max = parseInt(this.max);
                    const actual = parseInt(this.value) || 0;
                    validarAforo(actual, max);
                });
                inputEntrada.addEventListener('change', actualizarResumenPago);
                inputSalida.addEventListener('change', actualizarResumenPago);
                metodoPago.addEventListener('change', actualizarTextoBotonPago);

                function calcularNoches() {
                    if (!inputEntrada.value || !inputSalida.value) return 0;
                    const inicio = new Date(inputEntrada.value + "T00:00:00");
                    const fin = new Date(inputSalida.value + "T00:00:00");
                    const diff = fin - inicio;
                    const noches = Math.round(diff / (1000 * 60 * 60 * 24));
                    return (noches > 0) ? noches : 0;
                }

                function actualizarResumenPago() {
                    const noches = calcularNoches();
                    const total = noches * precioNocheActual;
                    totalNochesEl.textContent = noches;
                    importeTotalEl.innerHTML = `${total.toFixed(2)}&euro;`;
                    inputTotalEstimado.value = total.toFixed(2);
                }

                function actualizarTextoBotonPago() {
                    if (metodoPago.value === 'EFECTIVO') {
                        btnPagar.textContent = 'PAGAR EN EFECTIVO Y CONFIRMAR';
                    } else {
                        btnPagar.textContent = 'IR A PASARELA Y CONFIRMAR';
                    }
                }

                function validarAforo(actual, max) {
                    // Solo marcar error si el actual es mayor al mÃ¡ximo permitido
                    if (actual > max || actual <= 0) {
                        inputHuespedes.classList.add('is-invalid');
                        btnPagar.disabled = true;
                        btnPagar.classList.replace('btn-primary', 'btn-danger');
                        btnPagar.textContent = 'AFORO EXCEDIDO';
                    } else {
                        inputHuespedes.classList.remove('is-invalid');
                        btnPagar.disabled = false;
                        btnPagar.classList.replace('btn-danger', 'btn-primary');
                        actualizarTextoBotonPago();
                    }
                }
            });
        </script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            // Detectamos si en la URL viene el parÃ¡metro ?reserva=ok
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('reserva') === 'ok') {
                Swal.fire({
                    title: '\u00A1Reserva Confirmada!',
                    text: 'Tu estancia ha sido registrada correctamente. \u00A1Te esperamos!',
                    icon: 'success',
                    confirmButtonColor: '#198754',
                    confirmButtonText: 'Genial'
                }).then(() => {
                    // Limpiamos la URL para que no vuelva a salir el mensaje al recargar
                    window.history.replaceState({}, document.title, "habitaciones.php");
                });
            }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);

                // 1. Alerta de Ã‰XITO
                if (urlParams.get('reserva') === 'ok') {
                    Swal.fire({
                        title: '\u00A1Reserva Confirmada!',
                        text: 'Tu estancia ha sido registrada correctamente.',
                        icon: 'success',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Genial'
                    });
                    window.history.replaceState({}, document.title, "habitaciones.php");
                }

                // 2. Alerta de ERROR POR CAPACIDAD (Lo que pediste)
                if (urlParams.get('error') === 'capacidad_excedida') {
                    const maximo = urlParams.get('max');
                    Swal.fire({
                        title: '\u00A1Aforo superado!',
                        html: `Esta habitaci\u00F3n solo permite un m\u00E1ximo de <b>${maximo} personas</b>.<br><br>Por favor, ajusta el n\u00FAmero de hu\u00E9spedes para continuar.`,
                        icon: 'warning',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Entendido, volver a intentar'
                    }).then(() => {
                        // Limpiamos la URL para que no se repita el aviso al refrescar
                        window.history.replaceState({}, document.title, "habitaciones.php");
                    });
                }

                if (urlParams.get('error') === 'fecha_pasada') {
                    Swal.fire({
                        title: 'Fecha no valida',
                        text: 'No se puede reservar con fechas anteriores al dia actual.',
                        icon: 'warning',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        window.history.replaceState({}, document.title, "habitaciones.php");
                    });
                }

                if (urlParams.get('error') === 'rango_fechas') {
                    Swal.fire({
                        title: 'Rango de fechas invalido',
                        text: 'La fecha de salida no puede ser menor que la fecha de entrada.',
                        icon: 'warning',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        window.history.replaceState({}, document.title, "habitaciones.php");
                    });
                }
            });
        </script>


        <?php include "app/includes/pie.php"; ?>
