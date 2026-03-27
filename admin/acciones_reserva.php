<?php
require_once "../app/config/conexion.php";
require_once "../app/includes/seguridad_admin.php";

// --- LÓGICA PARA EL BUSCADOR (AJAX - GET) ---
if (isset($_GET['documento'])) {
    $dni = $_GET['documento'];
    $stmt = $conexion->prepare("SELECT * FROM persona WHERE documento = ?");
    $stmt->execute([$dni]);
    $persona = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($persona);
    exit;
}

// --- 1. GESTIÓN DE ELIMINAR (GET) ---
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $conexion->prepare("DELETE FROM reserva WHERE id_reserva = ?");
        $stmt->execute([$id]);
        header("Location: reservas.php?msg=eliminada");
        exit();
    } catch (Exception $e) {
        die("Error al eliminar: " . $e->getMessage());
    }
}

// --- 2. GESTIÓN DE PETICIONES POST (CREAR / EDITAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    $accion = $_POST['accion']; // <-- AQUÍ DEFINIMOS LA VARIABLE QUE FALTABA

   // --- LÓGICA DE EDICIÓN CORREGIDA ---
if ($accion === 'editar_reserva' || $accion === 'editar') {
    try {
        $conexion->beginTransaction(); // Iniciamos transacción para seguridad

        $id_reserva = $_POST['id_reserva'];
        $nuevo_total = floatval($_POST['total']);
        $id_habitacion = $_POST['id_habitacion'];
        
        // 1. Obtener el total antiguo para comparar
        $stmt = $conexion->prepare("SELECT total FROM reserva WHERE id_reserva = ?");
        $stmt->execute([$id_reserva]);
        $total_antiguo = $stmt->fetchColumn();

        // 2. Determinar el nuevo estado (si sube el precio, vuelve a PENDIENTE)
        $nuevo_estado = ($nuevo_total > $total_antiguo) ? 'PENDIENTE' : $_POST['estado'];

        // 3. ACTUALIZAR TABLA 'reserva' (Solo campos que existen ahí)
        $sqlReserva = "UPDATE reserva SET fecha_entrada=?, fecha_salida=?, num_huespedes=?, total=?, estado=? WHERE id_reserva=?";
        $stmtR = $conexion->prepare($sqlReserva);
        $stmtR->execute([
            $_POST['fecha_entrada'],
            $_POST['fecha_salida'],
            $_POST['num_huespedes'],
            $nuevo_total,
            $nuevo_estado,
            $id_reserva
        ]);

        // 4. ACTUALIZAR TABLA 'reserva_habitacion' (Donde vive realmente el id_habitacion)
        // Calculamos el nuevo precio por noche por si cambió
        $f_in = new DateTime($_POST['fecha_entrada']);
        $f_out = new DateTime($_POST['fecha_salida']);
        $noches = $f_in->diff($f_out)->days ?: 1;
        $precio_noche = $nuevo_total / $noches;

        $sqlHab = "UPDATE reserva_habitacion SET id_habitacion=?, precio_noche_aplicado=? WHERE id_reserva=?";
        $stmtH = $conexion->prepare($sqlHab);
        $stmtH->execute([$id_habitacion, $precio_noche, $id_reserva]);

        $conexion->commit();
        
        header("Location: reservas.php?msg=actualizada");
        exit();

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        die("Error al editar: " . $e->getMessage());
    }
}

    // --- LÓGICA DE CREACIÓN COMPLETA ---
    if ($accion === 'crear_reserva_completa') {
        try {
            $conexion->beginTransaction();

            $doc = $_POST['documento'] ?? '';
            $email = $_POST['email'] ?? '';
            $origen = $_POST['origen'] ?? 'reservas';
            $destino_ok = ($origen === 'ocupacion') ? 'ocupacion.php' : 'reservas.php';

            // --- PASO 1: MANEJO DE PERSONA ---
            $personaExistente = null;
            if ($email !== '') {
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
                // Evita cambiar nombre/apellidos de una persona existente por un alta de reserva.
                $stmtUpd = $conexion->prepare("UPDATE persona
                                               SET telefono = CASE WHEN (telefono IS NULL OR telefono = '') THEN ? ELSE telefono END
                                               WHERE id_persona = ?");
                $stmtUpd->execute([$_POST['telefono'], $id_persona]);
            } else {
                $stmt = $conexion->prepare("INSERT INTO persona (nombre, apellidos, documento, email, telefono) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['nombre'], $_POST['apellidos'], $doc, $email, $_POST['telefono']]);
                $id_persona = $conexion->lastInsertId();
            }

            // --- PASO 2: MANEJO DE CLIENTE ---
            $stmtCli = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_persona = ?");
            $stmtCli->execute([$id_persona]);
            $cliente = $stmtCli->fetch();
            
            if ($cliente) {
                $id_cliente = $cliente['id_cliente'];
            } else {
                $stmt = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (?)");
                $stmt->execute([$id_persona]);
                $id_cliente = $conexion->lastInsertId();
            }

            // --- PASO 3: INSERTAR RESERVA ---
            $metodo = strtoupper($_POST['metodo_pago'] ?? 'EFECTIVO');
            $estado_reserva = ($metodo === 'TARJETA' || $metodo === 'STRIPE') ? 'PENDIENTE' : 'CONFIRMADA';

            $stmt = $conexion->prepare("INSERT INTO reserva (id_cliente, fecha_entrada, fecha_salida, num_huespedes, total, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_cliente, 
                $_POST['fecha_entrada'], 
                $_POST['fecha_salida'], 
                $_POST['num_huespedes'], 
                $_POST['total'], 
                $estado_reserva
            ]);
            $id_reserva = $conexion->lastInsertId();

            // --- PASO 4: VINCULAR HABITACIÓN ---
            $stmt = $conexion->prepare("INSERT INTO reserva_habitacion (id_reserva, id_habitacion, precio_noche_aplicado) VALUES (?, ?, ?)");
            $f_in = new DateTime($_POST['fecha_entrada']);
            $f_out = new DateTime($_POST['fecha_salida']);
            $noches = $f_in->diff($f_out)->days ?: 1;
            $precio_noche = floatval($_POST['total']) / $noches;
            $stmt->execute([$id_reserva, $_POST['id_habitacion'], $precio_noche]);

            // --- PASO 5: REGISTRAR PAGO (Solo si es Efectivo) ---
            if ($metodo === 'EFECTIVO') {
                $stmt = $conexion->prepare("INSERT INTO pago (id_reserva, importe, metodo, pagado_en, estado) VALUES (?, ?, ?, NOW(), 'PAGADO')");
                $stmt->execute([$id_reserva, $_POST['total'], 'EFECTIVO']);
            }

            $conexion->commit();

            if ($metodo === 'TARJETA' || $metodo === 'STRIPE') {
                $fromPago = ($origen === 'ocupacion') ? 'admin_ocupacion' : 'admin';
                header("Location: ../procesar_pago.php?id_reserva=$id_reserva&total=" . $_POST['total'] . "&from=" . $fromPago);
                exit();
            } else {
                header("Location: {$destino_ok}?success=1");
                exit();
            }

        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            die("Error en la base de datos: " . $e->getMessage());
        }
    }
}
