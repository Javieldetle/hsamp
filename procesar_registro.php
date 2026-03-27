<?php
require_once "app/config/conexion.php";
session_start();

/* =========================
   Funciones de validación
========================= */

function es_email_valido($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function es_telefono_valido($telefono)
{
    // Acepta vacío o teléfono español básico (9 dígitos, opcional +34, espacios o guiones)
    if ($telefono === "") {
        return true;
    }

    $t = preg_replace('/[\s\-]/', '', $telefono);
    if (strpos($t, '+34') === 0) {
        $t = substr($t, 3);
    }

    return preg_match('/^\d{9}$/', $t) === 1;
}

function es_dni_nie_valido($doc)
{
    // Acepta vacío o DNI/NIE válido
    if ($doc === "") {
        return true;
    }

    $doc = strtoupper(trim($doc));
    $doc = str_replace([' ', '-', '.'], '', $doc);

    $letras = "TRWAGMYFPDXBNJZSQVHLCKE";

    // NIE: X/Y/Z + 7 dígitos + letra
    if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $doc)) {
        $map = ['X' => '0', 'Y' => '1', 'Z' => '2'];
        $num = $map[$doc[0]] . substr($doc, 1, 7);
        $letra = substr($doc, -1);
        return $letra === $letras[((int)$num) % 23];
    }

    // DNI: 8 dígitos + letra
    if (preg_match('/^\d{8}[A-Z]$/', $doc)) {
        $num = substr($doc, 0, 8);
        $letra = substr($doc, -1);
        return $letra === $letras[((int)$num) % 23];
    }

    return false;
}

function guardar_formulario_en_sesion($datos)
{
    $_SESSION["form_registro"] = $datos;
}

function fallar_registro($mensaje)
{
    $_SESSION["error_registro"] = $mensaje;
    header("Location: registro.php");
    exit;
}

/* =========================
   1) Leer datos del POST
========================= */

$nombre = trim($_POST["nombre"] ?? "");
$apellidos = trim($_POST["apellidos"] ?? "");
$email = trim($_POST["email"] ?? "");

$telefono = trim($_POST["telefono"] ?? "");
$documento = trim($_POST["documento"] ?? "");
$fecha_nacimiento = trim($_POST["fecha_nacimiento"] ?? "");

$direccion = trim($_POST["direccion"] ?? "");
$ciudad = trim($_POST["ciudad"] ?? "");
$provincia = trim($_POST["provincia"] ?? "");
$cp = trim($_POST["cp"] ?? "");
$pais = trim($_POST["pais"] ?? "");

$username = trim($_POST["username"] ?? "");
$clave = $_POST["clave"] ?? "";
$clave2 = $_POST["clave2"] ?? "";

/* Guardar datos en sesión para no perder el formulario (sin contraseñas) */
$datos_guardar = [
    "nombre" => $nombre,
    "apellidos" => $apellidos,
    "email" => $email,
    "telefono" => $telefono,
    "documento" => $documento,
    "fecha_nacimiento" => $fecha_nacimiento,
    "direccion" => $direccion,
    "ciudad" => $ciudad,
    "provincia" => $provincia,
    "cp" => $cp,
    "pais" => $pais,
    "username" => $username
];
guardar_formulario_en_sesion($datos_guardar);

/* =========================
   2) Validaciones obligatorias
========================= */

// Obligatorios
if ($nombre === "" || $apellidos === "" || $email === "" || $username === "" || $clave === "" || $clave2 === "") {
    fallar_registro("Completa los campos obligatorios.");
}

// Email
if (!es_email_valido($email)) {
    fallar_registro("El email no tiene un formato válido.");
}

// Teléfono
if (!es_telefono_valido($telefono)) {
    fallar_registro("El teléfono debe tener 9 dígitos (opcional +34).");
}

// DNI/NIE
if (!es_dni_nie_valido($documento)) {
    fallar_registro("El documento (DNI/NIE) no es válido.");
}

// Contraseñas iguales
if ($clave !== $clave2) {
    fallar_registro("Las contraseñas no coinciden.");
}

// Contraseña segura: min 10, 1 mayúscula, 1 número, 1 caracter especial
$patron_clave = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
if (!preg_match($patron_clave, $clave)) {
    fallar_registro("La contraseña debe tener mínimo 10 caracteres, 1 mayúscula, 1 número y 1 carácter especial.");
}

// Fecha
$fecha_nacimiento_sql = ($fecha_nacimiento !== "") ? $fecha_nacimiento : null;

/* Normalizar documento (si se envía) */
$documento_sql = null;
if ($documento !== "") {
    $documento_sql = strtoupper(trim($documento));
    $documento_sql = str_replace([' ', '-', '.'], '', $documento_sql);
}

/* =========================
   3) Insertar en BD (transacción)
========================= */

try {
    // Duplicado email
    $sql = "SELECT 1 FROM persona WHERE email = :email LIMIT 1";
    $st = $conexion->prepare($sql);
    $st->execute([":email" => $email]);
    if ($st->fetchColumn()) {
        fallar_registro("El email ya está registrado.");
    }

    // Duplicado username
    $sql = "SELECT 1 FROM usuario WHERE username = :u LIMIT 1";
    $st = $conexion->prepare($sql);
    $st->execute([":u" => $username]);
    if ($st->fetchColumn()) {
        fallar_registro("El nombre de usuario ya existe.");
    }

    $conexion->beginTransaction();

    /* Insertar persona */
    $sql = "INSERT INTO persona
            (documento, nombre, apellidos, email, telefono, direccion, ciudad, provincia, cp, pais, fecha_nacimiento)
            VALUES
            (:documento, :nombre, :apellidos, :email, :telefono, :direccion, :ciudad, :provincia, :cp, :pais, :fecha_nacimiento)";
    $st = $conexion->prepare($sql);
    $st->execute([
        ":documento" => $documento_sql,
        ":nombre" => $nombre,
        ":apellidos" => $apellidos,
        ":email" => $email,
        ":telefono" => ($telefono !== "" ? $telefono : null),
        ":direccion" => ($direccion !== "" ? $direccion : null),
        ":ciudad" => ($ciudad !== "" ? $ciudad : null),
        ":provincia" => ($provincia !== "" ? $provincia : null),
        ":cp" => ($cp !== "" ? $cp : null),
        ":pais" => ($pais !== "" ? $pais : null),
        ":fecha_nacimiento" => $fecha_nacimiento_sql
    ]);

    $id_persona = (int)$conexion->lastInsertId();

    /* Insertar usuario (CLIENTE) */
    $hash = password_hash($clave, PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuario (id_persona, username, password_hash, rol, activo)
            VALUES (:id_persona, :username, :password_hash, 'CLIENTE', 1)";
    $st = $conexion->prepare($sql);
    $st->execute([
        ":id_persona" => $id_persona,
        ":username" => $username,
        ":password_hash" => $hash
    ]);

    $id_usuario = (int)$conexion->lastInsertId();

    /* Insertar cliente */
    $sql = "INSERT INTO cliente (id_persona) VALUES (:id_persona)";
    $st = $conexion->prepare($sql);
    $st->execute([":id_persona" => $id_persona]);

    $conexion->commit();

    /* =========================
       4) Login automático (queda conectado)
    ========================= */
    $_SESSION["usuario_id"] = $id_usuario;
    $_SESSION["usuario_username"] = $username;
    $_SESSION["usuario_rol"] = "CLIENTE";

    /* Limpiar datos del formulario guardados */
    unset($_SESSION["form_registro"]);
    unset($_SESSION["error_registro"]);
    unset($_SESSION["error_registro_codigo"]);

    header("Location: index.php");
    exit;

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    fallar_registro("No se pudo completar el registro. Intenta nuevamente.");
}
?>
