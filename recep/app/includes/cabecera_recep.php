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
    <title>HSAMP - Recepción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../app/css/estilos.css"> 
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">HSAMP Recepción</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuRecep" aria-controls="menuRecep" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuRecep">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="clientes.php">Clientes</a></li>
                <li class="nav-item"><a class="nav-link" href="habitaciones.php">Ocupación</a></li>
                <li class="nav-item"><a class="nav-link" href="reservas.php">Reservas</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown position-relative">
                    <a id="recepUserToggle" class="nav-link dropdown-toggle" href="#" role="button" aria-expanded="false" onclick="toggleRecepUserMenu(event);">
                        Bienvenido/a, <?php echo htmlspecialchars($_SESSION["usuario_username"] ?? "Personal"); ?>
                    </a>
                    <ul id="recepUserMenu" class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php">Panel Recep</a></li>
                        <li><a class="dropdown-item" href="../index.php">Ver sitio</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../cerrar_sesion.php">Cerrar sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
function toggleRecepUserMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    const menu = document.getElementById("recepUserMenu");
    if (!menu) return;
    menu.classList.toggle("show");
}

document.addEventListener("click", function (e) {
    const btn = document.getElementById("recepUserToggle");
    const menu = document.getElementById("recepUserMenu");
    if (!btn || !menu) return;
    const dentroBoton = btn.contains(e.target);
    const dentroMenu = menu.contains(e.target);
    if (!dentroBoton && !dentroMenu) {
        menu.classList.remove("show");
    }
});
</script>
<div class="container mb-5 pb-5">
