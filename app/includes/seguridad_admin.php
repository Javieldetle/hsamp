<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php?abrir_login=1&redir=/hsamp/admin/index.php");
    exit;
}

if (!isset($_SESSION["usuario_rol"]) || $_SESSION["usuario_rol"] !== "ADMIN") {
    header("Location: ../index.php");
    exit;
}
?>
