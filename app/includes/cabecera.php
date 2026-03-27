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
    <title>HSAMP - Sistema de Reservas</title>

    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS propio -->
    <link rel="stylesheet" href="app/css/estilos.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">HSAMP</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <?php if (isset($_SESSION["usuario_id"])) { ?>
                        <li class="nav-item"><a class="nav-link" href="habitaciones.php">Habitaciones</a></li>
                        <li class="nav-item"><a class="nav-link" href="reservar.php">Reservas</a></li>
                    <?php } else { ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?abrir_login=1&redir=habitaciones.php">Habitaciones</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?abrir_login=1&redir=reservar.php">Reservas</a></li>
                    <?php } ?>

                    <?php if (isset($_SESSION["usuario_username"])) { ?>
                        <li class="nav-item dropdown ms-lg-2 position-relative">
                            <a id="siteUserToggle" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.preventDefault(); document.getElementById('siteUserMenu').classList.toggle('show');">
                                Hola, <?php echo htmlspecialchars($_SESSION["usuario_username"]); ?>
                            </a>
                            <ul id="siteUserMenu" class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php">Mi perfil</a></li>
                                <?php if (isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] === "ADMIN") { ?>
                                    <li><a class="dropdown-item" href="admin/index.php">Panel Admin</a></li>
                                <?php } elseif (isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] === "RECEPCION") { ?>
                                    <li><a class="dropdown-item" href="recep/index.php">Panel Recep</a></li>
                                <?php } ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="cerrar_sesion.php">Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php } else { ?>
                        <li class="nav-item ms-lg-2">
                            <button type="button" class="btn btn-outline-light px-3"
                                data-bs-toggle="modal" data-bs-target="#modalInicioSesion">
                                Iniciar sesión
                            </button>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </nav>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var btn = document.getElementById("siteUserToggle");
            var menu = document.getElementById("siteUserMenu");
            if (!btn || !menu) return;

            btn.addEventListener("click", function(e) {
                e.preventDefault();
                menu.classList.toggle("show");
            });

            document.addEventListener("click", function(e) {
                var dentroBoton = btn.contains(e.target);
                var dentroMenu = menu.contains(e.target);
                if (!dentroBoton && !dentroMenu) {
                    menu.classList.remove("show");
                }
            });
        });
    </script>

    <!-- =========================
     MODAL INICIO DE SESION
========================= -->
    <div class="modal fade" id="modalInicioSesion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <form action="procesar_iniciar_sesion.php" method="POST" autocomplete="off">
                    <input type="hidden" name="redir"
                        value="<?php echo isset($_GET["redir"]) ? htmlspecialchars($_GET["redir"]) : ""; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Iniciar sesión</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">

                        <?php if (isset($_GET["error"]) && $_GET["error"] === "login") { ?>
                            <div class="alert alert-danger mb-3">
                                Usuario o contraseña incorrectos.
                            </div>
                        <?php } ?>

                        <?php if (isset($_GET["ok"]) && $_GET["ok"] === "registro") { ?>
                            <div class="alert alert-success mb-3">
                                Registro completado. Ya puedes iniciar sesión.
                            </div>
                        <?php } ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre de usuario</label>
                            <input type="text" name="nombre_usuario" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="clave" class="form-control" required>
                        </div>

                    </div>

                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>

                        <div class="d-flex gap-2">
                            <a href="registro.php" class="btn btn-outline-primary">Registrarse</a>
                            <button type="submit" class="btn btn-primary">Entrar</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Abrir automáticamente el modal si hay error de login o registro correcto -->
    <?php if ((isset($_GET["error"]) && $_GET["error"] === "login") || (isset($_GET["ok"]) && $_GET["ok"] === "registro")) { ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var modalEl = document.getElementById("modalInicioSesion");
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    <?php } ?>

    <?php if (isset($_GET["abrir_login"]) && $_GET["abrir_login"] == "1") { ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var modalEl = document.getElementById("modalInicioSesion");
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    <?php } ?>

    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
