<?php
require_once "app/config/conexion.php";
include "app/includes/cabecera.php";
?>

<div class="container mt-5">

    <!-- Banner principal -->
    <div class="hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1 class="fw-bold">Bienvenido a Hostería Sampedro</h1>
                <p class="text-muted mt-3">
                    Sistema de reservas para la gestión de habitaciones y reservas online de la Hostería Sampedro.
                </p>

                <div class="d-flex gap-2 mt-4">
                    <?php if (isset($_SESSION["usuario_id"])) { ?>
                        <a href="reservar.php" class="btn btn-primary btn-lg">Reservar ahora</a>
                        <a href="habitaciones.php" class="btn btn-outline-secondary btn-lg">Ver habitaciones</a>
                    <?php } else { ?>
                        <a href="index.php?abrir_login=1&redir=reservar.php" class="btn btn-primary btn-lg">Reservar ahora</a>
                        <a href="index.php?abrir_login=1&redir=habitaciones.php" class="btn btn-outline-secondary btn-lg">Ver habitaciones</a>
                    <?php } ?>

                </div>
            </div>

            <div class="col-md-5 text-center mt-4 mt-md-0">
                <img src="https://cdn-icons-png.flaticon.com/512/201/201426.png" width="200" alt="Hotel">
            </div>
        </div>
    </div>

    <!-- Tarjetas informativas -->
    <div class="row mt-5 g-4">
        <div class="col-md-4">
            <div class="card tarjeta-info p-3">
                <h5 class="fw-bold">Reservas online</h5>
                <p class="text-muted mb-0">El cliente puede reservar de forma rápida y sencilla.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta-info p-3">
                <h5 class="fw-bold">Gestión completa</h5>
                <p class="text-muted mb-0">Panel de administración para controlar habitaciones y reservas.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta-info p-3">
                <h5 class="fw-bold">Pagos simulados</h5>
                <p class="text-muted mb-0">Integración de pago mediante Stripe (simulación).</p>
            </div>
        </div>
    </div>

</div>

<?php include "app/includes/pie.php"; ?>