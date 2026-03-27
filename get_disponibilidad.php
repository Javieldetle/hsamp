<?php
// get_disponibilidad.php
require_once "app/config/conexion.php";

$id_habitacion = $_GET['id'] ?? 0;

// Consultamos reservas CONFIRMADAS para bloquear esos días en el calendario
$query = "SELECT r.fecha_entrada, r.fecha_salida 
          FROM reserva r
          JOIN reserva_habitacion rh ON r.id_reserva = rh.id_reserva
          WHERE rh.id_habitacion = ? AND r.estado = 'CONFIRMADA'";

$stmt = $conexion->prepare($query);
$stmt->execute([$id_habitacion]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$eventos = [];
foreach ($reservas as $res) {
    $eventos[] = [
        'start'   => $res['fecha_entrada'],
        'end'     => date('Y-m-d', strtotime($res['fecha_salida'] . ' +1 day')), // Ajuste para FullCalendar
        'display' => 'background',
        'color'   => '#ff0000' // Color Rojo para ocupado
    ];
}

header('Content-Type: application/json');
echo json_encode($eventos);