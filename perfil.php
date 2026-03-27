<?php
require_once "app/config/conexion.php";
include "app/includes/cabecera.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php?error=login");
    exit;
}

$id_usuario = (int)$_SESSION["usuario_id"];

/* Mensajes (toast) */
$mensaje_ok = $_SESSION["perfil_ok"] ?? "";
$mensaje_error = $_SESSION["perfil_error"] ?? "";
unset($_SESSION["perfil_ok"]);
unset($_SESSION["perfil_error"]);

/* Cargar datos de persona asociados al usuario */
$sql = "SELECT p.id_persona, p.documento, p.nombre, p.apellidos, p.email, p.telefono,
               p.direccion, p.ciudad, p.provincia, p.cp, p.pais, p.fecha_nacimiento
        FROM usuario u
        INNER JOIN persona p ON p.id_persona = u.id_persona
        WHERE u.id_usuario = :id
        LIMIT 1";
$st = $conexion->prepare($sql);
$st->execute([":id" => $id_usuario]);
$datos = $st->fetch(PDO::FETCH_ASSOC);

if (!$datos) {
    header("Location: index.php");
    exit;
}

function h($v)
{
    return htmlspecialchars($v ?? "");
}

function fecha_es($fecha_sql)
{
    if ($fecha_sql === null || $fecha_sql === "") return "";
    $t = strtotime($fecha_sql);
    if ($t === false) return "";
    return date("d/m/Y", $t);
}

?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card tarjeta-info p-4">
                <h2 class="fw-bold mb-1">Mi perfil</h2>
                <p class="text-muted mb-4">Actualiza tus datos de contacto y tu contraseña.</p>

                <!-- TOAST -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3">
                    <div id="toastPerfil" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div id="toastPerfilTexto" class="toast-body">Mensaje</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                        </div>
                    </div>
                </div>

                <form id="formPerfil" action="procesar_perfil.php" method="POST" class="row g-3">

                    <h5 class="fw-bold mt-2">Datos personales</h5>

                    <div class="col-md-6">
                        <label class="form-label mb-0">Nombre</label>
                        <div class="form-control-plaintext fw-semibold">
                            <?php echo h($datos["nombre"]); ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label mb-0">Apellidos</label>
                        <div class="form-control-plaintext fw-semibold">
                            <?php echo h($datos["apellidos"]); ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label mb-0">Documento (DNI/NIE)</label>
                        <div class="form-control-plaintext fw-semibold">
                            <?php echo h($datos["documento"]); ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label mb-0">Fecha de nacimiento</label>
                        <div class="form-control-plaintext fw-semibold">
                            <?php echo h(fecha_es($datos["fecha_nacimiento"])); ?>
                        </div>
                       
                    </div>


                    <hr class="mt-4">

                    <h5 class="fw-bold">Datos de contacto</h5>

                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="120" required
                            value="<?php echo h($datos["email"]); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" maxlength="20"
                            value="<?php echo h($datos["telefono"]); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" maxlength="150"
                            value="<?php echo h($datos["direccion"]); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="80"
                            value="<?php echo h($datos["ciudad"]); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="80"
                            value="<?php echo h($datos["provincia"]); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Código postal</label>
                        <input type="text" name="cp" class="form-control" maxlength="10"
                            value="<?php echo h($datos["cp"]); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">País</label>
                        <input type="text" name="pais" class="form-control" maxlength="60"
                            value="<?php echo h($datos["pais"]); ?>">
                    </div>

                    <hr class="mt-4">

                    <h5 class="fw-bold">Cambiar contraseña</h5>
                    <p class="text-muted mb-0">Si no quieres cambiarla, deja estos campos vacíos.</p>

                    <div class="col-md-6">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" id="clave" name="clave_nueva" class="form-control" minlength="10">
                        <div class="form-text">Mínimo 10 caracteres, 1 mayúscula, 1 número y 1 carácter especial.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Repetir nueva contraseña</label>
                        <input type="password" id="clave2" name="clave_nueva2" class="form-control" minlength="10">
                    </div>

                    <div class="col-12 d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        <a href="index.php" class="btn btn-outline-secondary">Volver</a>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<?php if ($mensaje_ok !== "" || $mensaje_error !== "") { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var toastEl = document.getElementById("toastPerfil");
            var textoEl = document.getElementById("toastPerfilTexto");

            <?php if ($mensaje_ok !== "") { ?>
                toastEl.classList.add("text-bg-success");
                textoEl.textContent = <?php echo json_encode($mensaje_ok); ?>;
            <?php } else { ?>
                toastEl.classList.add("text-bg-danger");
                textoEl.textContent = <?php echo json_encode($mensaje_error); ?>;
            <?php } ?>

            var toast = new bootstrap.Toast(toastEl, {
                delay: 4500
            });
            toast.show();
        });
    </script>
<?php } ?>

<?php include "app/includes/pie.php"; ?>
