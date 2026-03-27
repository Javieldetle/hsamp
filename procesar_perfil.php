<?php
require_once "app/config/conexion.php";
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php?error=login");
    exit;
}

$id_usuario = (int)$_SESSION["usuario_id"];

/* =========================
   Validaciones
========================= */
function es_email_valido($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function es_telefono_valido($telefono)
{
    if ($telefono === "") return true;

    $t = preg_replace('/[\s\-]/', '', $telefono);
    if (strpos($t, '+34') === 0) $t = substr($t, 3);

    return preg_match('/^\d{9}$/', $t) === 1;
}

function es_clave_segura($clave)
{
    $patron = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
    return preg_match($patron, $clave) === 1;
}

function error_perfil($msg)
{
    $_SESSION["perfil_error"] = $msg;
    header("Location: perfil.php");
    exit;
}

function ok_perfil($msg)
{
    $_SESSION["perfil_ok"] = $msg;
    header("Location: perfil.php");
    exit;
}

/* =========================
   1) Leer SOLO lo editable
========================= */
$email = trim($_POST["email"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$direccion = trim($_POST["direccion"] ?? "");
$ciudad = trim($_POST["ciudad"] ?? "");
$provincia = trim($_POST["provincia"] ?? "");
$cp = trim($_POST["cp"] ?? "");
$pais = trim($_POST["pais"] ?? "");

/* Cambio de contraseña (opcional) */
$clave_nueva = $_POST["clave_nueva"] ?? "";
$clave_nueva2 = $_POST["clave_nueva2"] ?? "";

/* =========================
   2) Validaciones
========================= */
if ($email === "") {
    error_perfil("El email es obligatorio.");
}

if (!es_email_valido($email)) {
    error_perfil("El email no tiene un formato válido.");
}

if (!es_telefono_valido($telefono)) {
    error_perfil("El teléfono debe tener 9 dígitos (opcional +34).");
}

/* =========================
   3) Cargar usuario/persona
========================= */
$sql = "SELECT u.id_usuario, u.password_hash, u.id_persona
        FROM usuario u
        WHERE u.id_usuario = :id
        LIMIT 1";
$st = $conexion->prepare($sql);
$st->execute([":id" => $id_usuario]);
$usr = $st->fetch(PDO::FETCH_ASSOC);

if (!$usr) {
    error_perfil("No se pudo cargar tu usuario.");
}

$id_persona = (int)$usr["id_persona"];

/* Email duplicado (otra persona) */
$sql = "SELECT 1
        FROM persona
        WHERE email = :email
          AND id_persona <> :id_persona
        LIMIT 1";
$st = $conexion->prepare($sql);
$st->execute([":email" => $email, ":id_persona" => $id_persona]);
if ($st->fetchColumn()) {
    error_perfil("Ese email ya está registrado por otra cuenta.");
}

/* =========================
   4) Cambio de contraseña (si se intenta)
========================= */
$cambia_clave = false;
if ($clave_nueva !== "" || $clave_nueva2 !== "") {
    $cambia_clave = true;

    if ($clave_nueva === "" || $clave_nueva2 === "") {
        error_perfil("Para cambiar la contraseña, completa nueva contraseña y repetir nueva contraseña.");
    }

    if ($clave_nueva !== $clave_nueva2) {
        error_perfil("La nueva contraseña y su repetición no coinciden.");
    }

    if (!es_clave_segura($clave_nueva)) {
        error_perfil("La nueva contraseña debe tener mínimo 10 caracteres, 1 mayúscula, 1 número y 1 carácter especial.");
    }
}

/* =========================
   5) Guardar cambios
========================= */
try {
    $conexion->beginTransaction();

    /* SOLO actualizamos campos editables */
    $sql = "UPDATE persona
            SET email = :email,
                telefono = :telefono,
                direccion = :direccion,
                ciudad = :ciudad,
                provincia = :provincia,
                cp = :cp,
                pais = :pais
            WHERE id_persona = :id_persona";
    $st = $conexion->prepare($sql);
    $st->execute([
        ":email" => $email,
        ":telefono" => ($telefono !== "" ? $telefono : null),
        ":direccion" => ($direccion !== "" ? $direccion : null),
        ":ciudad" => ($ciudad !== "" ? $ciudad : null),
        ":provincia" => ($provincia !== "" ? $provincia : null),
        ":cp" => ($cp !== "" ? $cp : null),
        ":pais" => ($pais !== "" ? $pais : null),
        ":id_persona" => $id_persona
    ]);

    if ($cambia_clave) {
        $hash = password_hash($clave_nueva, PASSWORD_BCRYPT);

        $sql = "UPDATE usuario
                SET password_hash = :hash
                WHERE id_usuario = :id_usuario";
        $st = $conexion->prepare($sql);
        $st->execute([
            ":hash" => $hash,
            ":id_usuario" => $id_usuario
        ]);
    }

    $conexion->commit();
    ok_perfil("Datos actualizados correctamente.");

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_perfil("No se pudieron guardar los cambios. Intenta nuevamente.");
}
?>
