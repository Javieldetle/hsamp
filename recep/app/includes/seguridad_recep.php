<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificamos si el rol NO es RECEPCION ni ADMIN
if (!isset($_SESSION["usuario_rol"]) || ($_SESSION["usuario_rol"] !== 'RECEPCION' && $_SESSION["usuario_rol"] !== 'ADMIN')) {
    // Redirigimos a la raíz del proyecto hsamp
    header("Location: /hsamp/index.php?error=acceso_denegado");
    exit();
}
?>