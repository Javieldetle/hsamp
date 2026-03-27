<?php
// app/config/conexion.php

// 1. Iniciar la sesión si no se ha iniciado todavía
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servidor = "localhost";
$basedatos = "hsamp";
$usuario = "root";
$contrasena = "";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$basedatos;charset=utf8mb4", $usuario, $contrasena);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configuración opcional pero recomendada para obtener arrays asociativos por defecto
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}