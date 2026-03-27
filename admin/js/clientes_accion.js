document.addEventListener("DOMContentLoaded", function () {
    const URL_AJAX = "clientes.php?ajax=1";
    const tieneSwal = typeof Swal !== "undefined";

    const validarEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    const validarPass = (p) => {
        const tieneMayuscula = /[A-Z]/.test(p);
        const tieneNumero = /[0-9]/.test(p);
        const tieneEspecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(p);
        return tieneMayuscula && tieneNumero && tieneEspecial && p.length >= 10;
    };

    const mostrarMensaje = (tipo, titulo, texto) => {
        if (tieneSwal) {
            return Swal.fire({
                icon: tipo,
                title: titulo,
                text: texto,
                confirmButtonColor: "#0d6efd",
                confirmButtonText: "Aceptar"
            });
        }
        alert(texto);
        return Promise.resolve();
    };

    const confirmarAccion = async (titulo, texto) => {
        if (tieneSwal) {
            const r = await Swal.fire({
                icon: "warning",
                title: titulo,
                text: texto,
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Si, continuar",
                cancelButtonText: "Cancelar"
            });
            return r.isConfirmed === true;
        }
        return confirm(texto);
    };

    document.addEventListener("click", async function (e) {
        const btnEditar = e.target.closest(".btn-editar");
        if (btnEditar) {
            const data = JSON.parse(btnEditar.dataset.persona);
            document.getElementById("edit_id").value = data.id_persona;
            document.getElementById("edit_nom").value = data.nombre;
            document.getElementById("edit_ape").value = data.apellidos;
            document.getElementById("edit_ema").value = data.email;
            document.getElementById("edit_tel").value = data.telefono || "";
            document.getElementById("edit_doc").value = data.documento || "";
            document.getElementById("edit_ciu").value = data.ciudad || "";
            if (document.getElementById("edit_prov")) {
                document.getElementById("edit_prov").value = data.provincia || "";
            }
            document.getElementById("edit_dir").value = data.direccion || "";

            let m = new bootstrap.Modal(document.getElementById("modalEditar"));
            m.show();
        }

        const btnPass = e.target.closest(".btn-pass");
        if (btnPass) {
            document.getElementById("pass_id").value = btnPass.dataset.id;
            document.getElementById("new_password").value = "";
            let m = new bootstrap.Modal(document.getElementById("modalPass"));
            m.show();
        }

        const btnEli = e.target.closest(".btn-eliminar");
        if (btnEli) {
            const ok = await confirmarAccion(
                "Eliminar cliente",
                "Se borraran sus datos de acceso y su perfil personal."
            );
            if (!ok) return;

            const formData = new FormData();
            formData.append("accion", "eliminar");
            formData.append("id_persona", btnEli.dataset.id);

            const res = await fetch(URL_AJAX, { method: "POST", body: formData });
            const r = await res.json();
            await mostrarMensaje(
                r.ok ? "success" : "error",
                r.ok ? "Cliente eliminado" : "No se pudo eliminar",
                r.mensaje
            );
            if (r.ok) location.reload();
        }
    });

    document.getElementById("formEditar").addEventListener("submit", async function (e) {
        e.preventDefault();
        const ema = document.getElementById("edit_ema").value.trim();
        if (!validarEmail(ema)) {
            await mostrarMensaje("warning", "Email invalido", "El formato del email no es valido.");
            return;
        }

        const res = await fetch(URL_AJAX, { method: "POST", body: new FormData(this) });
        const r = await res.json();
        await mostrarMensaje(
            r.ok ? "success" : "error",
            r.ok ? "Cliente actualizado" : "No se pudo actualizar",
            r.mensaje
        );
        if (r.ok) location.reload();
    });

    document.getElementById("formPass").addEventListener("submit", async function (e) {
        e.preventDefault();
        const pass = document.getElementById("new_password").value;

        if (!validarPass(pass)) {
            await mostrarMensaje(
                "warning",
                "Contrasena invalida",
                "Debe tener minimo 10 caracteres, 1 mayuscula, 1 numero y 1 simbolo."
            );
            return;
        }

        const res = await fetch(URL_AJAX, { method: "POST", body: new FormData(this) });
        const r = await res.json();
        await mostrarMensaje(
            r.ok ? "success" : "error",
            r.ok ? "Clave actualizada" : "No se pudo actualizar",
            r.mensaje
        );
        if (r.ok) location.reload();
    });
});
