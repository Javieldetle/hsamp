<?php
require_once "app/includes/seguridad_cliente.php";
require_once "app/config/conexion.php";

$accion = $_REQUEST['accion'] ?? '';

// Nuevo flujo de cancelacion para cliente:
// permite cancelar mientras la reserva no haya finalizado (fecha_salida >= hoy)
if ($accion == 'eliminar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conexion->prepare("SELECT fecha_salida, estado FROM reserva WHERE id_reserva = ? AND id_cliente = ?");
    $stmt->execute([$id, $_SESSION['id_cliente']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        header("Location: reservar.php?msg=no_encontrada");
        exit;
    }

    if (strtoupper($res['estado']) === 'CANCELADA') {
        header("Location: reservar.php?msg=cancelada");
        exit;
    }

    $hoy = new DateTime(date('Y-m-d'));
    $f_salida = new DateTime($res['fecha_salida']);

    if ($f_salida >= $hoy) {
        $del = $conexion->prepare("UPDATE reserva SET estado = 'CANCELADA' WHERE id_reserva = ?");
        $del->execute([$id]);
        header("Location: reservar.php?msg=cancelada");
        exit;
    }

    header("Location: reservar.php?msg=no_permitida");
    exit;
}

if ($accion == 'eliminar' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Validar propiedad y tiempo (24h)
    $stmt = $conexion->prepare("SELECT fecha_entrada FROM reserva WHERE id_reserva = ? AND id_cliente = ?");
    $stmt->execute([$id, $_SESSION['id_cliente']]);
    $res = $stmt->fetch();

    if ($res) {
        $f_entrada = new DateTime($res['fecha_entrada']);
        $hoy = new DateTime();
        
        // Corrección de lógica de tiempo para DateTime
        if ($f_entrada > $hoy) {
            $intervalo = $hoy->diff($f_entrada);
            $horas = ($intervalo->days * 24) + $intervalo->h;

            if ($horas >= 24) {
                $del = $conexion->prepare("UPDATE reserva SET estado = 'CANCELADA' WHERE id_reserva = ?");
                $del->execute([$id]);
                header("Location: reservar.php?msg=cancelada");
                exit;
            } else {
                die("Error: Solo se puede cancelar con 24h de antelación.");
            }
        } else {
            die("Error: No puedes cancelar una reserva que ya ha comenzado o es pasada.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'crear_reserva_completa') {
        try {
            $conexion->beginTransaction();

            // 1. Insertar Persona (Cambiado 'dni' por 'documento')
            $stmt = $conexion->prepare("INSERT INTO persona (nombre, apellidos, documento, email, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['nombre'], 
                $_POST['apellidos'], 
                $_POST['dni'], // Este es el valor que viene del formulario
                $_POST['email'], 
                $_POST['telefono']
            ]);
            $id_persona = $conexion->lastInsertId();

            // 2. Insertar Cliente
            $stmt = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (?)");
            $stmt->execute([$id_persona]);
            $id_cliente = $conexion->lastInsertId();

            // 3. Insertar Reserva
            $stmt = $conexion->prepare("INSERT INTO reserva (id_cliente, fecha_entrada, fecha_salida, num_huespedes, total, estado) VALUES (?, ?, ?, ?, ?, 'CONFIRMADA')");
            $stmt->execute([
                $id_cliente, 
                $_POST['fecha_entrada'], 
                $_POST['fecha_salida'], 
                $_POST['num_huespedes'], 
                $_POST['total'], 
            ]);
            $id_reserva = $conexion->lastInsertId();

            // 4. Vincular Habitación (Añadido precio_noche_aplicado según tu SQL)
            $precio_noche = floatval($_POST['total']) / ( (strtotime($_POST['fecha_salida']) - strtotime($_POST['fecha_entrada'])) / 86400 );
            $stmt = $conexion->prepare("INSERT INTO reserva_habitacion (id_reserva, id_habitacion, precio_noche_aplicado) VALUES (?, ?, ?)");
            $stmt->execute([$id_reserva, $_POST['id_habitacion'], $precio_noche]);

            // 5. Registrar Pago (Ajustado a nombres de tu tabla 'pago')
            if (isset($_POST['marcar_pagado'])) {
                $stmt = $conexion->prepare("INSERT INTO pago (id_reserva, importe, metodo, estado, pagado_en) VALUES (?, ?, ?, 'PAGADO', NOW())");
                $stmt->execute([
                    $id_reserva, 
                    $_POST['total'], 
                    strtoupper($_POST['metodo_pago']) // STRIPE, EFECTIVO o TRANSFERENCIA
                ]);
            }

            $conexion->commit();
            header("Location: reservas.php?success=1");
            exit();

        } catch (Exception $e) {
            $conexion->rollBack();
            // Esto evitará la pantalla en blanco y te dirá exactamente qué falló
            echo "Error detallado: " . $e->getMessage();
        }
    }
}

if ($accion == 'editar' && isset($_POST['id_reserva'])) {
    $id_reserva = $_POST['id_reserva'];
    $nueva_entrada = $_POST['fecha_entrada'];
    $nueva_salida = $_POST['fecha_salida'];
    $huespedes = isset($_POST['num_huespedes']) ? (int)$_POST['num_huespedes'] : 0;
    $id_cliente = $_SESSION['id_cliente'];

    // 1. Validar propiedad, margen de 24h y capacidad
    // NOTA: Ya no necesitamos h.precio_noche de la tabla habitación, usaremos la tabla TARIFA
    $stmt = $conexion->prepare("SELECT r.fecha_entrada, r.num_huespedes, rh.id_habitacion
                                FROM reserva r 
                                JOIN reserva_habitacion rh ON r.id_reserva = rh.id_reserva 
                                WHERE r.id_reserva = ? AND r.id_cliente = ?");
    $stmt->execute([$id_reserva, $id_cliente]);
    $reserva_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva_actual) {
        die("Reserva no encontrada o no tienes permiso.");
    }

    // Si el formulario no envio huespedes, mantenemos el valor actual de la reserva
    if ($huespedes <= 0) {
        $huespedes = (int)$reserva_actual['num_huespedes'];
    }

    // Capacidad real calculada por plazas de la habitacion
    $stmtCap = $conexion->prepare("SELECT SUM(hp.cantidad * tp.plazas) AS capacidad_max
                                   FROM habitacion_plaza hp
                                   JOIN tipo_plaza tp ON hp.id_tipo_plaza = tp.id_tipo_plaza
                                   WHERE hp.id_habitacion = ?");
    $stmtCap->execute([$reserva_actual['id_habitacion']]);
    $capacidad_max = (int)($stmtCap->fetchColumn() ?: 0);

    if ($capacidad_max > 0 && $huespedes > $capacidad_max) {
        header("Location: reservar.php?error=capacidad_excedida&max=" . $capacidad_max);
        exit;
    }

    // Validación de 24h
    $f_entrada_original = new DateTime($reserva_actual['fecha_entrada']);
    $hoy = new DateTime();
    if ($f_entrada_original > $hoy) {
        $diff = $hoy->diff($f_entrada_original);
        $horas = ($diff->days * 24) + $diff->h;
        if ($horas < 24) {
            die("Error: Las modificaciones deben realizarse con al menos 24h de antelación.");
        }
    } else {
        die("Error: No se puede editar una reserva pasada o en curso.");
    }

    // 2. Validar disponibilidad
    $id_hab = $reserva_actual['id_habitacion'];
    $sql_dispo = "SELECT COUNT(*) FROM reserva_habitacion rh 
                  JOIN reserva r ON rh.id_reserva = r.id_reserva 
                  WHERE rh.id_habitacion = ? 
                  AND r.id_reserva != ? 
                  AND r.estado != 'CANCELADA'
                  AND (? < r.fecha_salida AND ? > r.fecha_entrada)";
    
    $stmt_dispo = $conexion->prepare($sql_dispo);
    $stmt_dispo->execute([$id_hab, $id_reserva, $nueva_entrada, $nueva_salida]);
    $ocupada = $stmt_dispo->fetchColumn();

    if ($ocupada > 0) {
        header("Location: reservar.php?error=no_disponible");
        exit;
    }

    // --- 3. NUEVO SISTEMA DE RECALCULO POR TARIFA ---
    // Buscamos el precio en la tabla tarifa según la cantidad de personas
    $stmt_tarifa = $conexion->prepare("SELECT precio_noche FROM tarifa WHERE cantidad_personas = ?");
    $stmt_tarifa->execute([$huespedes]);
    $precio_tarifa = $stmt_tarifa->fetchColumn();

    if (!$precio_tarifa) {
        // Si no hay tarifa exacta (ej. más de 5), podrías asignar un default o la máxima
        $precio_tarifa = 80; // Valor por defecto basado en tu tabla
    }

    $date1 = new DateTime($nueva_entrada);
    $date2 = new DateTime($nueva_salida);
    $noches = $date1->diff($date2)->days;
    
    if ($noches <= 0) {
        die("Error: La fecha de salida debe ser posterior a la de entrada.");
    }
    
    $nuevo_total = $noches * $precio_tarifa;

    // 4. Actualizar la base de datos
    try {
        $conexion->beginTransaction();

        $update = $conexion->prepare("UPDATE reserva SET 
                                      fecha_entrada = ?, 
                                      fecha_salida = ?, 
                                      num_huespedes = ?, 
                                      total = ? 
                                      WHERE id_reserva = ?");
        $update->execute([$nueva_entrada, $nueva_salida, $huespedes, $nuevo_total, $id_reserva]);

        $conexion->commit();
        header("Location: reservar.php?msg=editada");
    } catch (Exception $e) {
        $conexion->rollBack();
        die("Error al actualizar: " . $e->getMessage());
    }
}
