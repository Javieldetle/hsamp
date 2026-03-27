/**
 * ==========================================
 * FUNCIONES GLOBALES
 * (Deben estar en window para que los 'onclick' del HTML funcionen)
 * ==========================================
 */

// 1. Abrir modal para NUEVO servicio extra
window.nuevoExtra = function() {
    console.log("Abriendo modal para nuevo servicio...");
    
    const titulo = document.getElementById('tituloModalExtra');
    const form = document.getElementById('formExtra');
    const inputId = document.getElementById('id_extra'); // USAMOS id_extra, NO ex_id
    const modalEl = document.getElementById('modalExtra');

    if(titulo) titulo.textContent = "Nuevo Servicio Extra";
    if(form) form.reset();
    if(inputId) inputId.value = ""; // Aquí ya no fallará porque el ID coincide

    if (modalEl) {
        const miModal = new bootstrap.Modal(modalEl);
        miModal.show();
    } else {
        console.error("No se encontró el elemento modalExtra");
    }
};
// 2. Eliminar servicio extra con confirmación
window.eliminarExtra = function (id, nombre) {
    Swal.fire({
        title: `¿Eliminar ${nombre}?`,
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append("accion", "eliminar_extra");
            fd.append("id_extra", id);

            try {
                const res = await fetch("precios.php?ajax=1", { method: 'POST', body: fd });
                const r = await res.json();
                if (r.ok) {
                    Swal.fire('Eliminado', r.mensaje, 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (err) {
                Swal.fire('Error', "No se pudo eliminar", 'error');
            }
        }
    });
};

/**
 * ==========================================
 * MANEJO DE EVENTOS (DOM CONTENT LOADED)
 * ==========================================
 */
document.addEventListener("DOMContentLoaded", function () {
    const URL_AJAX = "precios.php?ajax=1";

    // Configuración de notificaciones tipo Toast
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    /**
     * 1. GUARDAR PRECIOS DE HABITACIÓN (TABLA SUPERIOR)
     */
    document.addEventListener("click", async function (e) {
        const btn = e.target.closest(".btn-save-h");
        if (btn) {
            const idRow = btn.dataset.id; 
            const idTipo = btn.dataset.idTipo; 

            const fd = new FormData();
            fd.append("accion", "actualizar_precios_habitacion");
            fd.append("id_tipo", idTipo);
            
            // Recolectar valores de los inputs p1 a p5 de la fila
            fd.append("p1", document.getElementById(`p1_${idRow}`).value);
            fd.append("p2", document.getElementById(`p2_${idRow}`).value);
            fd.append("p3", document.getElementById(`p3_${idRow}`).value);
            fd.append("p4", document.getElementById(`p4_${idRow}`).value);
            fd.append("p5", document.getElementById(`p5_${idRow}`).value);

            try {
                btn.disabled = true;
                const res = await fetch(URL_AJAX, { method: 'POST', body: fd });
                const r = await res.json();
                
                if (r.ok) {
                    Toast.fire({ icon: 'success', title: r.mensaje });
                    btn.classList.replace('btn-primary', 'btn-success');
                    setTimeout(() => {
                        btn.classList.replace('btn-success', 'btn-primary');
                        btn.disabled = false;
                    }, 1500);
                }
            } catch (err) {
                btn.disabled = false;
                Toast.fire({ icon: 'error', title: 'Error al guardar' });
            }
        }
    });

    /**
     * 2. EDITAR EXTRA (CARGAR DATOS EN MODAL)
     */
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".btn-edit-extra");
        if (btn) {
            // Cambiar título
            const titulo = document.getElementById('tituloModalExtra');
            if(titulo) titulo.textContent = "Editar Servicio";
            
            // Cargar datos en los inputs del modal
            document.getElementById('ex_id').value = btn.dataset.id;
            document.getElementById('ex_nom').value = btn.dataset.nombre;
            document.getElementById('ex_pre').value = btn.dataset.precio;
            
            // Inicializar y mostrar modal
            const modalEl = document.getElementById('modalExtra');
            const miModal = new bootstrap.Modal(modalEl);
            miModal.show();
        }
    });

    /**
     * 3. FORMULARIO EXTRA (SUBMIT PARA GUARDAR O ACTUALIZAR)
     */
    const formExtra = document.getElementById('formExtra');
    if (formExtra) {
        formExtra.addEventListener("submit", async function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append("accion", "guardar_extra");

            try {
                const res = await fetch(URL_AJAX, { method: 'POST', body: fd });
                const r = await res.json();
                
                if (r.ok) {
                    // Cerrar el modal
                    const modalEl = document.getElementById('modalExtra');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if(modalInstance) modalInstance.hide();
                    
                    Toast.fire({ icon: 'success', title: r.mensaje });
                    // Recargar para refrescar la lista
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Toast.fire({ icon: 'error', title: r.mensaje });
                }
            } catch (err) {
                Toast.fire({ icon: 'error', title: "Error al procesar la solicitud" });
            }
        });
    }
});