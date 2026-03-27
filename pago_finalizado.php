<?php
// pago_finalizado.php

require_once __DIR__ . "/app/config/conexion.php";
require_once __DIR__ . "/app/config/stripe_config.php";

// Capturamos los datos que llegan por la URL desde Stripe
$id_reserva = $_GET['id_reserva'] ?? null;
$id_sesion = $_GET['id_sesion'] ?? null;

// Detectamos si el flujo viene desde administrador o recepcion
$origen = $_GET['from'] ?? '';
$es_admin = ($origen === 'admin');
$es_admin_ocupacion = ($origen === 'admin_ocupacion');
$es_recep = ($origen === 'recep' || $origen === 'recep_reservas' || $origen === 'recep_habitaciones');
$es_recep_habitaciones = ($origen === 'recep_habitaciones');

if ($id_reserva && $id_sesion) {
    try {
        // Recuperamos la sesion de Stripe para confirmar el pago
        $sesion_stripe = \Stripe\Checkout\Session::retrieve($id_sesion);

        if ($sesion_stripe->payment_status === 'paid') {
            // Convertimos los centimos de Stripe a Euros
            $importe = $sesion_stripe->amount_total / 100;
            // Guardamos el Payment Intent como referencia unica
            $referencia = $sesion_stripe->payment_intent;

            $conexion->beginTransaction();

            // 1. Verificamos si este pago ya se registro (evita duplicados)
            $checkPago = $conexion->prepare("SELECT id_pago FROM pago WHERE referencia = ?");
            $checkPago->execute([$referencia]);
            
            if (!$checkPago->fetch()) {
                // Insertar el registro en la tabla 'pago'
                $sql_pago = "INSERT INTO pago (id_reserva, metodo, estado, importe, moneda, referencia, pagado_en) 
                             VALUES (?, 'STRIPE', 'PAGADO', ?, 'EUR', ?, NOW())";
                $stmt_pago = $conexion->prepare($sql_pago);
                $stmt_pago->execute([$id_reserva, $importe, $referencia]);
            }

            // 2. Actualizar el estado de la reserva a CONFIRMADA
            $sql_reserva = "UPDATE reserva SET estado = 'CONFIRMADA' WHERE id_reserva = ?";
            $stmt_reserva = $conexion->prepare($sql_reserva); 
            $stmt_reserva->execute([$id_reserva]);

            $conexion->commit();

            // 3. REDIRECCION DINAMICA
            if ($es_admin_ocupacion) {
                header("Location: admin/ocupacion.php?success=pagado");
            } elseif ($es_admin) {
                header("Location: admin/reservas.php?success=pagado");
            } elseif ($es_recep_habitaciones) {
                header("Location: recep/habitaciones.php?success=pagado");
            } elseif ($es_recep) {
                header("Location: recep/reservas.php?success=pagado");
            } else {
                header("Location: reservar.php?exito=pago_realizado");
            }
            exit;

        } else {
            // Si el pago no se completo
            $url_error = ($es_admin || $es_admin_ocupacion)
                ? "admin/reservas.php?error=pago_incompleto"
                : ($es_recep_habitaciones ? "recep/habitaciones.php?error=pago_incompleto" : ($es_recep ? "recep/reservas.php?error=pago_incompleto" : "reservar.php?error=pago_incompleto"));
            header("Location: $url_error");
            exit;
        }
    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        die("Error procesando el pago: " . $e->getMessage());
    }
} else {
    header("Location: reservar.php");
    exit;
}
