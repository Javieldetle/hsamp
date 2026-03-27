<?php
require_once "../app/config/conexion.php";

$busqueda = $_GET['q'] ?? '';

if (strlen($busqueda) < 3) {
    echo json_encode([]);
    exit;
}

// Buscamos por documento, nombre o apellidos
$stmt = $conexion->prepare("SELECT id_persona, documento, nombre, apellidos, email, telefono 
                            FROM persona 
                            WHERE documento LIKE ? OR apellidos LIKE ? OR nombre LIKE ? 
                            LIMIT 5");
$termino = "%$busqueda%";
$stmt->execute([$termino, $termino, $termino]);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resultados);