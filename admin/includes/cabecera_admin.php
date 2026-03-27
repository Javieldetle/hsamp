<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HSAMP - Panel de Administración</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS propio (opcional, reutilizamos el mismo) -->
    <link rel="stylesheet" href="../app/css/estilos.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="index.php">HSAMP | Admin</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuAdmin">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuAdmin">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                <li class="nav-item"><a class="nav-link" href="empleados.php">Empleados</a></li>
                <li class="nav-item"><a class="nav-link" href="habitaciones.php">Habitaciones</a></li>
                <li class="nav-item"><a class="nav-link" href="ocupacion.php">Ocupación</a></li>
                <li class="nav-item"><a class="nav-link" href="precios.php">Precios</a></li>
                <li class="nav-item"><a class="nav-link" href="reservas.php">Reservas</a></li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item position-relative">
                    <a id="adminUserToggle" class="nav-link dropdown-toggle" href="#" role="button" aria-expanded="false">
                        Conectado: <?php echo htmlspecialchars($_SESSION["usuario_username"] ?? "Admin"); ?>
                    </a>
                    <ul id="adminUserMenu" style="display:none; position:absolute; right:0; top:100%; z-index:3000; list-style:none; margin:0; padding:8px 0; min-width:220px; background:#1f2937; border:1px solid #374151; border-radius:8px;">
                        <li><a style="display:block; padding:8px 14px; color:#f8f9fa; text-decoration:none;" href="index.php">Panel Admin</a></li>
                        <li><a style="display:block; padding:8px 14px; color:#f8f9fa; text-decoration:none;" href="../perfil.php">Mi perfil</a></li>
                        <li><a style="display:block; padding:8px 14px; color:#f8f9fa; text-decoration:none;" href="../index.php">Mi sitio</a></li>
                        <li><hr style="margin:6px 0; border-color:#374151;"></li>
                        <li><a style="display:block; padding:8px 14px; color:#ff6b6b; text-decoration:none;" href="../cerrar_sesion.php">Cerrar sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">
<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("adminUserToggle");
    const menu = document.getElementById("adminUserMenu");
    if (!btn || !menu) return;

    btn.addEventListener("click", function (e) {
        e.preventDefault();
        menu.style.display = (menu.style.display === "block") ? "none" : "block";
    });

    document.addEventListener("click", function (e) {
        const dentroBoton = btn.contains(e.target);
        const dentroMenu = menu.contains(e.target);
        if (!dentroBoton && !dentroMenu) {
            menu.style.display = "none";
        }
    });
});
</script>
