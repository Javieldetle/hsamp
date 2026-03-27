<?php
require_once "app/config/conexion.php";
include "app/includes/cabecera.php";

/* =========================
   Recuperar datos y errores desde sesión
========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$form = $_SESSION["form_registro"] ?? [];
$mensaje_error = $_SESSION["error_registro"] ?? "";

/* Limpiar mensajes (para que no se repitan) */
unset($_SESSION["error_registro"]);
unset($_SESSION["error_registro_codigo"]);

function valor_form($form, $campo)
{
    return isset($form[$campo]) ? htmlspecialchars($form[$campo]) : "";
}
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card tarjeta-info p-4">
                <h2 class="fw-bold mb-1">Registro de cliente</h2>
                <p class="text-muted">Crea tu cuenta para realizar reservas en Hostería Sampedro.</p>

                <!-- TOAST (popup pequeño) -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3">
                    <div id="toastErrorRegistro" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div id="toastErrorTexto" class="toast-body">Error</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                        </div>
                    </div>
                </div>

                <form id="formRegistro" action="procesar_registro.php" method="POST" class="row g-3 mt-1">

                    <!-- DATOS PERSONALES -->
                    <h5 class="fw-bold mt-2">Datos personales</h5>

                    <div class="col-md-6">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" maxlength="60" required
                               value="<?php echo valor_form($form, "nombre"); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Apellidos *</label>
                        <input type="text" name="apellidos" class="form-control" maxlength="80" required
                               value="<?php echo valor_form($form, "apellidos"); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="120" required
                               value="<?php echo valor_form($form, "email"); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" maxlength="20"
                               value="<?php echo valor_form($form, "telefono"); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Documento (DNI/NIE)</label>
                        <input type="text" id="documento" name="documento" class="form-control" maxlength="20"
                               value="<?php echo valor_form($form, "documento"); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control"
                               value="<?php echo valor_form($form, "fecha_nacimiento"); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" maxlength="150"
                               value="<?php echo valor_form($form, "direccion"); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="80"
                               value="<?php echo valor_form($form, "ciudad"); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="80"
                               value="<?php echo valor_form($form, "provincia"); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Código postal</label>
                        <input type="text" name="cp" class="form-control" maxlength="10"
                               value="<?php echo valor_form($form, "cp"); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">País</label>
                        <input type="text" name="pais" class="form-control" maxlength="60"
                               value="<?php echo valor_form($form, "pais"); ?>">
                    </div>

                    <hr class="mt-4">

                    <!-- DATOS DE ACCESO -->
                    <h5 class="fw-bold">Datos de acceso</h5>

                    <div class="col-md-6">
                        <label class="form-label">Nombre de usuario *</label>
                        <input type="text" name="username" class="form-control" maxlength="50" required
                               value="<?php echo valor_form($form, "username"); ?>">
                        <div class="form-text">Será el nombre con el que iniciarás sesión.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" id="clave" name="clave" class="form-control" minlength="10" required>
                        <div class="form-text">
                            Mínimo 10 caracteres, 1 mayúscula, 1 número y 1 carácter especial.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Repetir contraseña *</label>
                        <input type="password" id="clave2" name="clave2" class="form-control" minlength="10" required>
                    </div>

                    <div class="col-12 d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success">Crear cuenta</button>
                        <a href="index.php" class="btn btn-outline-secondary">Volver</a>
                    </div>

                    <div class="col-12 mt-2">
                        <small class="text-muted">Los campos marcados con * son obligatorios.</small>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<?php if ($mensaje_error !== "") { ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const toastEl = document.getElementById("toastErrorRegistro");
    const texto = document.getElementById("toastErrorTexto");
    texto.textContent = <?php echo json_encode($mensaje_error); ?>;
    const toast = new bootstrap.Toast(toastEl, { delay: 4500 });
    toast.show();
});
</script>
<?php } ?>

<?php
include "app/includes/pie.php";
?>
