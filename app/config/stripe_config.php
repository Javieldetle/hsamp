<?php
// app/config/stripe_config.php

// Carga claves desde variables de entorno para no versionar secretos
$pk = getenv('STRIPE_PUBLIC_KEY') ?: 'STRIPE_PUBLIC_KEY_AQUI';
$sk = getenv('STRIPE_SECRET_KEY') ?: 'STRIPE_SECRET_KEY_AQUI';

define('CLAVE_PUBLICA_STRIPE', $pk);
define('CLAVE_SECRETA_STRIPE', $sk);

$ruta_init = __DIR__ . '/../vendor/stripe/stripe-php/init.php';
if (file_exists($ruta_init)) {
    require_once $ruta_init;
} else {
    die('Error: No se encuentra Stripe en app/vendor.');
}

\Stripe\Stripe::setApiKey(CLAVE_SECRETA_STRIPE);
