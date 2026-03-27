<?php
require_once "app/config/conexion.php";
require_once "app/includes/seguridad_recep.php";
$destino_recep = "/hsamp/recep/reservas.php";

// --- 1. ELIMINAR ---
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $conexion->prepare("DELETE FROM reserva WHERE id_reserva = ?");
        $stmt->execute([$id]);
        header("Location: {$destino_recep}?msg=eliminada");
        exit();
    } catch (Exception $e) {
        die("Error al eliminar: " . $e->getMessage());
    }
}

// --- 2. CREAR / EDITAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // --- EDITAR ---
    if ($accion === 'editar_reserva' || $accion === 'editar') {
        try {
            $conexion->beginTransaction();

            $id_reserva = (int)$_POST['id_reserva'];
            $nuevo_total = (float)$_POST['total'];
            $id_habitacion = (int)$_POST['id_habitacion'];
            $fecha_entrada = $_POST['fecha_entrada'];
            $fecha_salida = $_POST['fecha_salida'];
            $num_huespedes = (int)$_POST['num_huespedes'];

            // Validar rango de fechas
            if (strtotime($fecha_salida) <= strtotime($fecha_entrada)) {
                throw new Exception("Rango de fechas invalido.");
            }

            // Validar disponibilidad de la habitacion
            $stmtDisp = $conexion->prepare("SELECT COUNT(*)
                                            FROM reserva_habitacion rh
                                            JOIN reserva r ON rh.id_reserva = r.id_reserva
                                            WHERE rh.id_habitacion = ?
                                              AND r.id_reserva != ?
                                              AND r.estado != 'CANCELADA'
                                              AND (? < r.fecha_salida AND ? > r.fecha_entrada)");
            $stmtDisp->execute([$id_habitacion, $id_reserva, $fecha_entrada, $fecha_salida]);
            $ocupada = (int)$stmtDisp->fetchColumn();
            if ($ocupada > 0) {
                $conexion->rollBack();
                header("Location: {$destino_recep}?error=no_disponible");
                exit();
            }

            $stmt = $conexion->prepare("SELECT total FROM reserva WHERE id_reserva = ?");
            $stmt->execute([$id_reserva]);
            $total_antiguo = (float)$stmt->fetchColumn();

            $nuevo_estado = ($nuevo_total > $total_antiguo) ? 'PENDIENTE' : $_POST['estado'];

            $sqlReserva = "UPDATE reserva SET fecha_entrada=?, fecha_salida=?, num_huespedes=?, total=?, estado=? WHERE id_reserva=?";
            $stmtR = $conexion->prepare($sqlReserva);
            $stmtR->execute([$fecha_entrada, $fecha_salida, $num_huespedes, $nuevo_total, $nuevo_estado, $id_reserva]);

            $f_in = new DateTime($fecha_entrada);
            $f_out = new DateTime($fecha_salida);
            $noches = $f_in->diff($f_out)->days ?: 1;
            $precio_noche = $nuevo_total / $noches;

            $sqlHab = "UPDATE reserva_habitacion SET id_habitacion=?, precio_noche_aplicado=? WHERE id_reserva=?";
            $stmtH = $conexion->prepare($sqlHab);
            $stmtH->execute([$id_habitacion, $precio_noche, $id_reserva]);

            $conexion->commit();
            header("Location: {$destino_recep}?msg=actualizada");
            exit();
        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            die("Error al editar: " . $e->getMessage());
        }
    }

    // --- CREAR ---
    if ($accion === 'crear_reserva_completa') {
        try {
            $conexion->beginTransaction();

            $doc = trim($_POST['documento'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $id_persona_existente = (int)($_POST['id_persona_existente'] ?? 0);
            $id_habitacion = (int)($_POST['id_habitacion'] ?? 0);
            $fecha_entrada = $_POST['fecha_entrada'] ?? '';
            $fecha_salida = $_POST['fecha_salida'] ?? '';
            $num_huespedes = (int)($_POST['num_huespedes'] ?? 1);
            $total = (float)($_POST['total'] ?? 0);
            $metodo = strtoupper($_POST['metodo_pago'] ?? 'EFECTIVO');
            $origen = $_POST['origen'] ?? 'reservas';

            if ($id_habitacion <= 0 || $fecha_entrada === '' || $fecha_salida === '' || $num_huespedes <= 0) {
                throw new Exception("Datos de reserva incompletos.");
            }

            if (strtotime($fecha_salida) <= strtotime($fecha_entrada)) {
                $conexion->rollBack();
                header("Location: {$destino_recep}?error=fechas_invalidas");
                exit();
            }

            // Validar disponibilidad de la habitacion por fechas
            $stmtDisp = $conexion->prepare("SELECT COUNT(*)
                                            FROM reserva_habitacion rh
                                            JOIN reserva r ON rh.id_reserva = r.id_reserva
                                            WHERE rh.id_habitacion = ?
                                              AND r.estado != 'CANCELADA'
                                              AND (? < r.fecha_salida AND ? > r.fecha_entrada)");
            $stmtDisp->execute([$id_habitacion, $fecha_entrada, $fecha_salida]);
            $ocupada = (int)$stmtDisp->fetchColumn();
            if ($ocupada > 0) {
                $conexion->rollBack();
                header("Location: {$destino_recep}?error=no_disponible");
                exit();
            }

            // Persona
            $personaExistente = null;
            if ($id_persona_existente > 0) {
                $stmtCheckId = $conexion->prepare("SELECT id_persona FROM persona WHERE id_persona = ? LIMIT 1");
                $stmtCheckId->execute([$id_persona_existente]);
                $personaExistente = $stmtCheckId->fetch(PDO::FETCH_ASSOC);
            }
            if (!$personaExistente && $email !== '') {
                $stmtCheckEmail = $conexion->prepare("SELECT id_persona FROM persona WHERE email = ? LIMIT 1");
                $stmtCheckEmail->execute([$email]);
                $personaExistente = $stmtCheckEmail->fetch(PDO::FETCH_ASSOC);
            }
            if (!$personaExistente && $doc !== '') {
                $stmtCheckDoc = $conexion->prepare("SELECT id_persona FROM persona WHERE documento = ? LIMIT 1");
                $stmtCheckDoc->execute([$doc]);
                $personaExistente = $stmtCheckDoc->fetch(PDO::FETCH_ASSOC);
            }

            if ($personaExistente) {
                $id_persona = (int)$personaExistente['id_persona'];
                // Evitamos sobrescribir nombre/apellidos de personas ya existentes.
                // Solo completamos telefono/email/documento si faltan.
                $stmtUpd = $conexion->prepare("UPDATE persona
                                               SET telefono = CASE WHEN (telefono IS NULL OR telefono = '') THEN ? ELSE telefono END,
                                                   email = CASE WHEN (email IS NULL OR email = '') THEN ? ELSE email END,
                                                   documento = CASE WHEN (documento IS NULL OR documento = '') THEN ? ELSE documento END
                                               WHERE id_persona = ?");
                $stmtUpd->execute([$telefono, $email, $doc, $id_persona]);
            } else {
                $stmtInsP = $conexion->prepare("INSERT INTO persona (nombre, apellidos, documento, email, telefono) VALUES (?, ?, ?, ?, ?)");
                $stmtInsP->execute([$nombre, $apellidos, $doc, $email, $telefono]);
                $id_persona = (int)$conexion->lastInsertId();
            }

            // Cliente
            $stmtCli = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_persona = ?");
            $stmtCli->execute([$id_persona]);
            $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);
            if ($cliente) {
                $id_cliente = (int)$cliente['id_cliente'];
            } else {
                $stmtInsC = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (?)");
                $stmtInsC->execute([$id_persona]);
                $id_cliente = (int)$conexion->lastInsertId();
            }

            // Reserva
            $estado_reserva = ($metodo === 'TARJETA' || $metodo === 'STRIPE') ? 'PENDIENTE' : 'CONFIRMADA';
            $stmtRes = $conexion->prepare("INSERT INTO reserva (id_cliente, fecha_entrada, fecha_salida, num_huespedes, total, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtRes->execute([$id_cliente, $fecha_entrada, $fecha_salida, $num_huespedes, $total, $estado_reserva]);
            $id_reserva = (int)$conexion->lastInsertId();

            // Reserva habitacion
            $f_in = new DateTime($fecha_entrada);
            $f_out = new DateTime($fecha_salida);
            $noches = $f_in->diff($f_out)->days ?: 1;
            $precio_noche = $total / $noches;
            $stmtRh = $conexion->prepare("INSERT INTO reserva_habitacion (id_reserva, id_habitacion, precio_noche_aplicado) VALUES (?, ?, ?)");
            $stmtRh->execute([$id_reserva, $id_habitacion, $precio_noche]);

            // Pago inmediato si efectivo o transferencia
            if ($metodo === 'EFECTIVO' || $metodo === 'TRANSFERENCIA') {
                $stmtPago = $conexion->prepare("INSERT INTO pago (id_reserva, importe, metodo, pagado_en, estado) VALUES (?, ?, ?, NOW(), 'PAGADO')");
                $stmtPago->execute([$id_reserva, $total, ($metodo === 'TRANSFERENCIA' ? 'TRANSFERENCIA' : 'EFECTIVO')]);
            }

            $conexion->commit();

            if ($metodo === 'TARJETA' || $metodo === 'STRIPE') {
                $fromPago = ($origen === 'habitaciones') ? 'recep_habitaciones' : 'recep_reservas';
                header("Location: ../procesar_pago.php?id_reserva=$id_reserva&total=" . $_POST['total'] . "&from=" . $fromPago);
                exit();
            }

            header("Location: {$destino_recep}?success=1");
            exit();
        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            die("Error en la base de datos: " . $e->getMessage());
        }
    }
}
