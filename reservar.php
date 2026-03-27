<?php
require_once "app/includes/seguridad_cliente.php";
require_once "app/config/conexion.php";
include "app/includes/cabecera.php";

$id_cliente_actual = $_SESSION["id_cliente"];

// Consulta mejorada: obtenemos datos de habitaciÃ³n, cliente y ahora tambiÃ©n verificamos el estado del pago
$query = "SELECT r.*, h.numero, th.nombre as tipo_hab, h.precio_noche, 
                 p.nombre as cliente_nombre, p.apellidos as cliente_apellidos,
                 (SELECT estado FROM pago WHERE id_reserva = r.id_reserva LIMIT 1) as estado_pago
          FROM reserva r
          JOIN cliente c ON r.id_cliente = c.id_cliente
          JOIN persona p ON c.id_persona = p.id_persona
          JOIN reserva_habitacion rh ON r.id_reserva = rh.id_reserva
          JOIN habitacion h ON rh.id_habitacion = h.id_habitacion
          JOIN tipo_habitacion th ON h.id_tipo = th.id_tipo
          WHERE r.id_cliente = ? 
          ORDER BY r.fecha_entrada DESC";

$stmt = $conexion->prepare($query);
$stmt->execute([$id_cliente_actual]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoy = new DateTime();
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-calendar-check"></i> Mis Reservas</h2>
        <a href="habitaciones.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Reserva</a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'pago_ok'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-check-circle-fill"></i> &iexcl;Pago completado!</strong> Tu reserva ha sido confirmada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'cancelada'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-check-circle-fill"></i> Reserva cancelada.</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'no_permitida'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-exclamation-triangle-fill"></i> No se puede cancelar.</strong> La reserva ya finalizo.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (count($reservas) > 0): ?>
        <div class="table-responsive shadow-sm rounded">
            <table class="table table-hover align-middle bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Cliente</th>
                        <th>Habitaci&oacute;n</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Hu&eacute;spedes</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservas as $res): 
                        $f_entrada = new DateTime($res['fecha_entrada']);
                        $f_salida = new DateTime($res['fecha_salida']);
                        $diferencia = $hoy->diff($f_entrada);
                        $horas_restantes = ($diferencia->days * 24) + $diferencia->h;
                        
                        // Solo se puede editar/cancelar si faltan mÃ¡s de 24h y no estÃ¡ cancelada
                        $puede_gestionar = ($f_entrada > $hoy && $horas_restantes >= 24);
                        $puede_cancelar = (strtoupper($res['estado']) != 'CANCELADA' && $f_salida >= new DateTime(date('Y-m-d')));
                        $esta_pagada = ($res['estado_pago'] == 'PAGADO');
                    ?>
                        <tr>
                            <td>
                                <i class="bi bi-person-circle text-primary"></i> 
                                <?php echo htmlspecialchars($res['cliente_nombre'] . " " . $res['cliente_apellidos']); ?>
                            </td>
                            <td>
                                <strong>#<?php echo $res['numero']; ?></strong><br>
                                <small class="text-muted"><?php echo $res['tipo_hab']; ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($res['fecha_entrada'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($res['fecha_salida'])); ?></td>
                            <td><?php echo $res['num_huespedes']; ?></td>
                            <td class="fw-bold"><?php echo number_format($res['total'], 2); ?>&euro;</td>
                            <td>
                                <?php if ($res['estado'] == 'CANCELADA'): ?>
                                    <span class="badge bg-danger">CANCELADA</span>
                                <?php elseif ($esta_pagada): ?>
                                    <span class="badge bg-success">CONFIRMADA (PAGADA)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">PENDIENTE DE PAGO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if (!$esta_pagada && $res['estado'] != 'CANCELADA'): ?>
                                        <form action="procesar_pago.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id_reserva" value="<?php echo $res['id_reserva']; ?>">
                                            <input type="hidden" name="total_pago" value="<?php echo $res['total']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success text-white me-1">
                                                <i class="bi bi-credit-card"></i> Pagar
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($puede_gestionar && $res['estado'] != 'CANCELADA'): ?>
                                        <button class="btn btn-sm btn-primary text-white me-1" 
                                                onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($res)); ?>)">
                                            <i class="bi bi-pencil"></i> Modificar reserva
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($puede_cancelar): ?>
                                        <button class="btn btn-sm btn-danger text-white" 
                                                onclick="confirmarCancelacion(<?php echo $res['id_reserva']; ?>)">
                                            <i class="bi bi-trash"></i> Cancelar
                                        </button>
                                    <?php elseif ($res['estado'] != 'CANCELADA'): ?>
                                        <button class="btn btn-sm btn-danger text-white me-1" disabled title="Solo se permite cancelar con mas de 24h de antelacion">
                                            <i class="bi bi-trash"></i> Cancelar
                                        </button>
                                        <span class="text-muted small px-2">Bloqueado</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">A&uacute;n no has realizado ninguna reserva. <a href="habitaciones.php" class="fw-bold">&iexcl;Ver disponibilidad ahora!</a></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="acciones_reserva.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Modificar Reserva</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_reserva" id="edit_id_reserva">
                    <input type="hidden" name="accion" value="editar">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de Entrada</label>
                        <input type="date" name="fecha_entrada" id="edit_entrada" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de Salida</label>
                        <input type="date" name="fecha_salida" id="edit_salida" class="form-control" required>
                    </div>
                    <p class="text-muted small">Nota: El precio total se recalcular&aacute; autom&aacute;ticamente seg&uacute;n las nuevas fechas.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function abrirEditar(reserva) {
    document.getElementById('edit_id_reserva').value = reserva.id_reserva;
    document.getElementById('edit_entrada').value = reserva.fecha_entrada;
    document.getElementById('edit_salida').value = reserva.fecha_salida;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function confirmarCancelacion(id) {
    Swal.fire({
        title: '\u00BFEst\u00E1s seguro?',
        text: "Esta acci\u00F3n marcar\u00E1 la reserva como CANCELADA y solo es permitida con 24h de antelaci\u00F3n.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'S\u00ED, cancelar reserva',
        cancelButtonText: 'No, mantener'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `acciones_reserva.php?accion=eliminar&id=${id}`;
        }
    })
}
</script>

<?php include "app/includes/pie.php"; ?>
