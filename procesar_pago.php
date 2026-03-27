<?php

/**
 * procesar_pago.php
 * Ubicacion: htdocs/hsamp/procesar_pago.php
 */

require_once __DIR__ . "/app/config/conexion.php";
require_once __DIR__ . "/app/config/stripe_config.php";

// 1. CAPTURA DE DATOS FLEXIBLE
// Soporta POST (Formulario web) y GET (Redireccion Panel)
$id_reserva = $_POST['id_reserva'] ?? $_GET['id_reserva'] ?? null;

// Soportamos 'total_pago' (Web publica) y 'total' (Panel)
$total = $_POST['total_pago'] ?? $_GET['total'] ?? 0;

// DETECCION CLAVE: origen del pago (admin / recep / publico)
$origen = $_GET['from'] ?? '';
$es_admin = ($origen === 'admin');
$es_admin_ocupacion = ($origen === 'admin_ocupacion');
$es_recep = ($origen === 'recep' || $origen === 'recep_reservas' || $origen === 'recep_habitaciones');
$es_recep_habitaciones = ($origen === 'recep_habitaciones');

// 2. VALIDACION DE SEGURIDAD
if (!$id_reserva || $total <= 0) {
    $url_error = ($es_admin || $es_admin_ocupacion)
        ? "admin/reservas.php?error=datos_invalidos"
        : ($es_recep_habitaciones ? "recep/habitaciones.php?error=datos_invalidos" : ($es_recep ? "recep/reservas.php?error=datos_invalidos" : "reservar.php?error=datos_invalidos"));

    header("Location: $url_error");
    exit;
}

try {
    // 3. CONFIGURACION DE SESION DE STRIPE
    $sesion = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => 'Reserva Hosteria Sampedro',
                    'description' => 'Pago por la reserva n. ' . $id_reserva,
                ],
                // Stripe requiere centimos: 40.00 -> 4000
                'unit_amount' => (int)($total * 100),
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',

        // 4. REDIRECCION DE EXITO DINAMICA
        // Si es admin, vuelve a su panel. Si es recepcion, vuelve a su panel.
        // Si es cliente, vuelve a su vista publica.
        'success_url' => 'http://localhost/hsamp/pago_finalizado.php?id_reserva=' . $id_reserva . '&id_sesion={CHECKOUT_SESSION_ID}' . (($es_admin || $es_admin_ocupacion) ? '&from=' . urlencode($origen) : ($es_recep ? '&from=' . urlencode($origen) : '')),

        // 5. REDIRECCION DE CANCELACION DINAMICA
        'cancel_url' => ($es_admin || $es_admin_ocupacion)
            ? 'http://localhost/hsamp/admin/reservas.php?error=cancelado'
            : ($es_recep_habitaciones ? 'http://localhost/hsamp/recep/habitaciones.php?error=cancelado' : ($es_recep ? 'http://localhost/hsamp/recep/reservas.php?error=cancelado' : 'http://localhost/hsamp/reservar.php?error=cancelado')),

        'metadata' => [
            'id_reserva' => $id_reserva,
            'origen' => (($es_admin || $es_admin_ocupacion) ? $origen : ($es_recep ? $origen : 'publico'))
        ]
    ]);

    // 6. REDIRECCION A LA PASARELA
    header("Location: " . $sesion->url);
    exit;
} catch (Exception $e) {
    die("Error critico al conectar con Stripe: " . $e->getMessage());
}
