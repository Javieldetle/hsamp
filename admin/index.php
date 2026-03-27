<?php
require_once "../app/includes/seguridad_admin.php";
require_once "../app/config/conexion.php";
include "includes/cabecera_admin.php";
?>

<div class="row g-4">
    <div class="col-12">
        <h1 class="fw-bold">Panel de administración</h1>
        <p class="text-muted mb-0">Gestión de clientes, empleados, habitaciones, precios y reservas.</p>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <h5 class="fw-bold mb-1">Clientes</h5>
            <p class="text-muted mb-2">Alta y edición</p>
            <a href="clientes.php" class="btn btn-sm btn-primary">Gestionar</a>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <h5 class="fw-bold mb-1">Empleados</h5>
            <p class="text-muted mb-2">Cuentas y permisos</p>
            <a href="empleados.php" class="btn btn-sm btn-primary">Gestionar</a>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <h5 class="fw-bold mb-1">Habitaciones</h5>
            <p class="text-muted mb-2">Tipos, plazas, estado</p>
            <a href="habitaciones.php" class="btn btn-sm btn-primary">Gestionar</a>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card p-3">
            <h5 class="fw-bold mb-1">Reservas</h5>
            <p class="text-muted mb-2">Control y seguimiento</p>
            <a href="reservas.php" class="btn btn-sm btn-primary">Gestionar</a>
        </div>
    </div>
</div>

<?php include "includes/pie_admin.php"; ?>
