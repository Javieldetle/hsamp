<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Si ni siquiera hay un usuario logueado, a la portada
if (!isset($_SESSION["usuario_id"])) {
    $url = $_SERVER["REQUEST_URI"];
    header("Location: index.php?abrir_login=1&redir=" . urlencode($url));
    exit;
}

// 2. Si hay usuario pero no tenemos el id_cliente, vamos a buscarlo una sola vez
// Esto evita el error "Undefined array key id_cliente"
if (!isset($_SESSION["id_cliente"])) {
    require_once __DIR__ . "/../config/conexion.php";
    
    // Buscamos el id_cliente a través del id_persona vinculado al usuario
    $stmt = $conexion->prepare("SELECT c.id_cliente 
                                FROM cliente c 
                                JOIN usuario u ON c.id_persona = u.id_persona 
                                WHERE u.id_usuario = ?");
    $stmt->execute([$_SESSION["usuario_id"]]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $_SESSION["id_cliente"] = $cliente['id_cliente'];
    } else {
        // Si el usuario logueado no es un cliente (por ejemplo, un admin sin ficha de cliente)
        die("Error: Tu cuenta de usuario no tiene un perfil de cliente asociado.");
    }
}
?>