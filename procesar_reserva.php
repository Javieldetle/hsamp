<?php
session_start(); 
require_once "app/config/conexion.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_usuario_sesion = $_SESSION['usuario_id'] ?? null;

    if (!$id_usuario_sesion) {
        header("Location: login.php");
        exit;
    }

    $id_habitacion = $_POST['id_habitacion'];
    $fecha_entrada = $_POST['fecha_entrada'];
    $fecha_salida  = $_POST['fecha_salida'];
    $num_huespedes = (int)$_POST['num_huespedes'];
    $metodo_pago = strtoupper($_POST['metodo_pago'] ?? 'TARJETA');

    // Validacion de fechas: no permitir dias anteriores al actual
    $hoy = date('Y-m-d');
    if ($fecha_entrada < $hoy || $fecha_salida < $hoy) {
        header("Location: habitaciones.php?error=fecha_pasada");
        exit;
    }

    // Validacion basica de rango
    if ($fecha_salida < $fecha_entrada) {
        header("Location: habitaciones.php?error=rango_fechas");
        exit;
    }

    try {
        $conexion->beginTransaction();

        // 1. VALIDAR CAPACIDAD REAL DE PLAZAS (Blindaje de seguridad)
        $sql_cap = "SELECT SUM(hp.cantidad * tp.plazas) as total_plazas 
                    FROM habitacion_plaza hp
                    JOIN tipo_plaza tp ON hp.id_tipo_plaza = tp.id_tipo_plaza
                    WHERE hp.id_habitacion = ?";
        $stmt_cap = $conexion->prepare($sql_cap);
        $stmt_cap->execute([$id_habitacion]);
        $capacidad_max = $stmt_cap->fetchColumn();

        if ($num_huespedes > $capacidad_max) {
            throw new Exception("La habitación seleccionada solo permite un máximo de $capacidad_max personas.");
        }

        // 2. BUSCAR EL ID_CLIENTE REAL
        $stmt_c = $conexion->prepare("SELECT c.id_cliente 
                                     FROM cliente c 
                                     JOIN usuario u ON c.id_persona = u.id_persona 
                                     WHERE u.id_usuario = ?");
        $stmt_c->execute([$id_usuario_sesion]);
        $cliente = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new Exception("No se encontró un perfil de cliente para este usuario.");
        }
        $id_cliente = $cliente['id_cliente'];

        // 3. Obtener precio por noche
        $stmt_p = $conexion->prepare("SELECT precio_noche FROM habitacion WHERE id_habitacion = ?");
        $stmt_p->execute([$id_habitacion]);
        $hab = $stmt_p->fetch(PDO::FETCH_ASSOC);
        $precio_noche = $hab['precio_noche'];

        // 4. Calcular total
        $inicio = new DateTime($fecha_entrada);
        $fin = new DateTime($fecha_salida);
        $noches = $inicio->diff($fin)->days;
        if($noches <= 0) $noches = 1; 
        $total = $noches * $precio_noche;

        // 5. Insertar en tabla 'reserva'
        $estado_reserva = ($metodo_pago === 'EFECTIVO') ? 'CONFIRMADA' : 'PENDIENTE';
        $sql_reserva = "INSERT INTO reserva (id_cliente, fecha_entrada, fecha_salida, num_huespedes, total, estado, creada_en) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt_r = $conexion->prepare($sql_reserva);
        $stmt_r->execute([$id_cliente, $fecha_entrada, $fecha_salida, $num_huespedes, $total, $estado_reserva]);
        
        $id_reserva = $conexion->lastInsertId();

        // 6. Vincular en 'reserva_habitacion'
        $sql_rh = "INSERT INTO reserva_habitacion (id_reserva, id_habitacion, precio_noche_aplicado) VALUES (?, ?, ?)";
        $stmt_rh = $conexion->prepare($sql_rh);
        $stmt_rh->execute([$id_reserva, $id_habitacion, $precio_noche]);

        // 7. Si es pago en efectivo, registrar el pago al momento
        if ($metodo_pago === 'EFECTIVO') {
            $sql_pago = "INSERT INTO pago (id_reserva, importe, metodo, estado, pagado_en) VALUES (?, ?, 'EFECTIVO', 'PAGADO', NOW())";
            $stmt_pago = $conexion->prepare($sql_pago);
            $stmt_pago->execute([$id_reserva, $total]);
        }

        $conexion->commit();

        // 8. Flujo final segun metodo de pago
        if ($metodo_pago === 'TARJETA') {
            header("Location: procesar_pago.php?id_reserva=$id_reserva&total=$total");
            exit;
        }

        header("Location: habitaciones.php?reserva=ok");
        exit;

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        // Redirigir con error para que el cliente lo vea en SweetAlert
        header("Location: habitaciones.php?error=capacidad_excedida&max=" . ($capacidad_max ?? '0'));
        exit;
    }
}
