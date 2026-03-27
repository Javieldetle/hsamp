<?php
session_start();

/* Vaciar variables de sesión */
session_unset();

/* Destruir sesión */
session_destroy();

/* Volver al inicio */
header("Location: index.php");
exit;
?>
