document.addEventListener("DOMContentLoaded", function () {
    const URL_AJAX = "empleados.php?ajax=1";
    const modalEmp = new bootstrap.Modal(document.getElementById('modalEmpleado'));
    const modalPass = new bootstrap.Modal(document.getElementById('modalPass'));

    // --- REGLAS DE VALIDACIÓN ---
    const vEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    const vTel = (t) => /^[0-9]{9}$/.test(t);
    const vDNI = (d) => /^[0-9XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$/i.test(d);
    
    // REGLA: 10 caracteres, Mayúscula, Número y Símbolo
    const vPass = (p) => {
        const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{10,}$/;
        return regex.test(p);
    };

    const msgPassError = "La contraseña debe tener:\n- Mínimo 10 caracteres\n- Al menos 1 Mayúscula\n- Al menos 1 Número\n- Al menos 1 Símbolo especial";

    // --- BOTÓN NUEVO ---
    document.getElementById("btnNuevoEmpleado").addEventListener("click", () => {
        document.getElementById("formEmpleado").reset();
        document.getElementById("emp_id").value = "";
        document.getElementById("emp_accion").value = "crear";
        document.getElementById("modalTitle").innerText = "Registrar Nuevo Recepcionista";
        document.getElementById("div_pass").style.display = "block";
        document.getElementById("emp_pass").required = true;
        modalEmp.show();
    });

    // --- CLICS DINÁMICOS ---
    document.addEventListener("click", async function (e) {
        const btnEdit = e.target.closest(".btn-editar");
        const btnClave = e.target.closest(".btn-pass");
        const btnBorrar = e.target.closest(".btn-eliminar"); // ESTA LÍNEA FALTABA

        // Lógica Editar
        if (btnEdit) {
            const d = JSON.parse(btnEdit.dataset.emp);
            document.getElementById("emp_id").value = d.id_persona;
            document.getElementById("emp_nom").value = d.nombre;
            document.getElementById("emp_ape").value = d.apellidos;
            document.getElementById("emp_ema").value = d.email;
            document.getElementById("emp_tel").value = d.telefono || "";
            document.getElementById("emp_doc").value = d.documento || "";
            document.getElementById("emp_nac").value = d.fecha_nacimiento;
            document.getElementById("emp_sal").value = d.salario;
            document.getElementById("emp_cp").value = d.cp || "";
            document.getElementById("emp_ciu").value = d.ciudad || "";
            document.getElementById("emp_pai").value = d.pais || "";
            document.getElementById("emp_dir").value = d.direccion || "";
            
            document.getElementById("emp_accion").value = "editar";
            document.getElementById("modalTitle").innerText = "Editar Perfil de Empleado";
            document.getElementById("div_pass").style.display = "none";
            document.getElementById("emp_pass").required = false;
            modalEmp.show();
        }

        // Lógica Cambio Clave
        if (btnClave) {
            document.getElementById("pass_id").value = btnClave.dataset.id;
            document.getElementById("new_password").value = "";
            modalPass.show();
        }

        // Lógica Borrar
        if (btnBorrar) {
            const id = btnBorrar.dataset.id;
            if (confirm("¿Estás seguro de eliminar este empleado? Se borrarán sus datos de acceso y contrato.")) {
                const fd = new FormData();
                fd.append("accion", "eliminar");
                fd.append("id_persona", id);

                try {
                    const res = await fetch(URL_AJAX, { method: "POST", body: fd });
                    const r = await res.json();
                    alert(r.mensaje);
                    if (r.ok) location.reload();
                } catch (err) {
                    console.error(err);
                    alert("Error al procesar la eliminación.");
                }
            }
        }
    });

    // --- GUARDAR EMPLEADO (CREAR/EDITAR) ---
    document.getElementById("formEmpleado").addEventListener("submit", async function (e) {
        e.preventDefault();
        
        if (document.getElementById("emp_accion").value === "crear") {
            if (!vPass(document.getElementById("emp_pass").value)) {
                return alert(msgPassError);
            }
        }

        const res = await fetch(URL_AJAX, { method: "POST", body: new FormData(this) });
        const r = await res.json();
        alert(r.mensaje);
        if (r.ok) location.reload();
    });

    // --- CAMBIAR CLAVE ---
    document.getElementById("formPass").addEventListener("submit", async function (e) {
        e.preventDefault();
        const nuevaClave = document.getElementById("new_password").value;

        if (!vPass(nuevaClave)) {
            return alert("Contraseña débil:\n" + msgPassError);
        }

        const res = await fetch(URL_AJAX, { method: "POST", body: new FormData(this) });
        const r = await res.json();
        alert(r.mensaje);
        if (r.ok) modalPass.hide();
    });
});