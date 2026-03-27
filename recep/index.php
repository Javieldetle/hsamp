<?php
require_once "app/includes/seguridad_recep.php"; 
require_once "app/config/conexion.php";
include "app/includes/cabecera_recep.php"; 
?>

<div class="row g-4 mb-5">
    <div class="col-12">
        <h1 class="fw-bold">Panel de Recepción</h1>
        <p class="text-muted mb-4">Gestión operativa diaria: entradas, salidas y atención al cliente.</p>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column">
                <h5 class="fw-bold mb-2">Clientes</h5>
                <p class="text-muted flex-grow-1">Registro de huéspedes y actualización de perfiles en la base de datos.</p>
                <a href="clientes.php" class="btn btn-primary w-100 mt-3">Gestionar Clientes</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column">
                <h5 class="fw-bold mb-2">Estado de Ocupación</h5>
                <p class="text-muted flex-grow-1">Consulta de disponibilidad de habitaciones y estado de limpieza actual.</p>
                <a href="habitaciones.php" class="btn btn-primary w-100 mt-3">Ver Habitaciones</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column">
                <h5 class="fw-bold mb-2">Reservas y Pagos</h5>
                <p class="text-muted flex-grow-1">Gestión de Check-in, Check-out y control de cobros pendientes.</p>
                <a href="reservas.php" class="btn btn-primary w-100 mt-3">Gestionar Reservas</a>
            </div>
        </div>
    </div>
</div>

<?php include "app/includes/pie_recep.php"; ?>