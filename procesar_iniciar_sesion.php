<?php
ob_start(); // evita que cualquier salida rompa header()
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/app/config/conexion.php";
session_start();

/* Si se entra a este archivo sin enviar el formulario, volvemos al inicio */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /hsamp/index.php?error=login");
    exit;
}

/* =========================
   1) Leer datos
========================= */
$nombre_usuario = trim($_POST["nombre_usuario"] ?? "");
$clave = $_POST["clave"] ?? "";

if ($nombre_usuario === "" || $clave === "") {
    header("Location: /hsamp/index.php?error=login");
    exit;
}

/* =========================
   2) Buscar usuario
========================= */
// Seleccionamos 'rol' para saber si es ADMIN o CLIENTE
$sql = "SELECT id_usuario, username, password_hash, rol, activo
        FROM usuario
        WHERE username = :u
        LIMIT 1";
$st = $conexion->prepare($sql);
$st->execute([":u" => $nombre_usuario]);
$usuario = $st->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header("Location: /hsamp/index.php?error=login");
    exit;
}

if ((int)$usuario["activo"] !== 1) {
    header("Location: /hsamp/index.php?error=login");
    exit;
}

/* =========================
   3) Verificar contraseña
========================= */
if (!password_verify($clave, $usuario["password_hash"])) {
    header("Location: /hsamp/index.php?error=login");
    exit;
}

/* =========================
   4) Guardar sesión
========================= */
$_SESSION["usuario_id"] = (int)$usuario["id_usuario"];
$_SESSION["usuario_username"] = (string)$usuario["username"];
$_SESSION["usuario_rol"] = (string)$usuario["rol"]; // Aquí se guarda 'ADMIN' o 'CLIENTE'

/* =========================
   5) Actualizar último acceso
========================= */
$sql = "UPDATE usuario
        SET ultimo_acceso = NOW()
        WHERE id_usuario = :id";
$st = $conexion->prepare($sql);
$st->execute([":id" => (int)$usuario["id_usuario"]]);

/* =========================
   6) Redirección Inteligente
========================= */

$rol = strtoupper($_SESSION["usuario_rol"]); // Lo pasamos a mayúsculas para evitar fallos

// 1. Si es ADMINISTRADOR
if ($rol === 'ADMIN') {
    header("Location: admin/index.php");
    exit;
}

// 2. Si es RECEPCIÓN (Añadimos esta condición)
if ($rol === 'RECEPCION') {
    header("Location: recep/index.php");
    exit;
}

// 3. Si es CLIENTE, comprobamos si venía de una página específica
$redir = $_POST["redir"] ?? "";
if ($redir !== "") {
    header("Location: " . $redir);
    exit;
}

// Redirección por defecto para clientes normales
header("Location: index.php");
exit;