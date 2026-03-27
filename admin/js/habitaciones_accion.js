(function () {
    const URL_AJAX = "habitaciones.php?ajax=1";

    /**
     * Función para mostrar notificaciones al usuario
     * Busca un elemento con id "toastMensaje" o usa alert()
     */
function mostrarToast(texto) {
    const toastEl = document.getElementById("toastMensaje");
    const txt = document.getElementById("toastTexto");
    
    if (toastEl && txt) {
        txt.textContent = texto;
        const toast = new bootstrap.Toast(toastEl, { delay: 2000 });
        toast.show();
    } else {
        // En lugar de alert(texto), usamos console.log
        // Así el usuario no tiene que darle a "Aceptar"
        console.log("Notificación del sistema: " + texto);
    }
}

    /**
     * Función genérica para enviar datos vía POST y recibir JSON
     */
    async function postAccion(datos) {
        const formData = new FormData();
        for (const k in datos) {
            formData.append(k, datos[k]);
        }
        try {
            const res = await fetch(URL_AJAX, { 
                method: "POST", 
                body: formData 
            });
            return await res.json();
        } catch (error) {
            console.error("Error en postAccion:", error);
            return { ok: false, mensaje: "Error de conexión con el servidor." };
        }
    }

    /* =========================================================
       1. ACTUALIZAR TARIFAS (MODAL PRECIOS)
    ========================================================= */
    const formTarifas = document.getElementById('formGuardarTarifas');
    if (formTarifas) {
        formTarifas.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            // Nos aseguramos de que la acción sea la correcta
            formData.set('accion', 'guardar_tarifas');

            try {
                const res = await fetch(URL_AJAX, { 
                    method: 'POST', 
                    body: formData 
                });
                const r = await res.json();
                
                if (r.ok) {
                    mostrarToast(r.mensaje);
                    // Recargamos para que los nuevos precios se vean en el modal al abrirlo de nuevo
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast("Error: " + r.mensaje);
                }
            } catch (error) {
                console.error(error);
                mostrarToast("Error crítico al guardar tarifas.");
            }
        });
    }

    /* =========================================================
       2. CREAR HABITACIÓN
    ========================================================= */
    const formCrear = document.getElementById("formCrearHabitacion");
    if (formCrear) {
        formCrear.addEventListener("submit", async function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            
            try {
                const res = await fetch(URL_AJAX, { 
                    method: "POST", 
                    body: fd 
                });
                const r = await res.json();
                
                if (r.ok) {
                    mostrarToast("Habitación creada con éxito.");
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast("Error: " + r.mensaje);
                }
            } catch (error) {
                mostrarToast("Error al procesar la solicitud de creación.");
            }
        });
    }

    /* =========================================================
       3. EVENTOS DINÁMICOS (SUMAR, RESTAR, ELIMINAR)
    ========================================================= */
    document.addEventListener("click", async function (e) {
        
        // --- MANEJO DE SUMAR/RESTAR CAMAS ---
        if (e.target.classList.contains("btn-plaza")) {
            const btn = e.target;
            const accion = btn.dataset.accion;
            const idHab = btn.dataset.idHabitacion;
            const idTipoPlaza = btn.dataset.idTipoPlaza;

            const r = await postAccion({
                accion: accion,
                id_habitacion: idHab,
                id_tipo_plaza: idTipoPlaza
            });

            if (!r.ok) {
                mostrarToast(r.mensaje);
                return;
            }

            // Actualizar el número de la cama específica en el HTML
            const spanCant = document.getElementById(`cant_${idHab}_${idTipoPlaza}`);
            if (spanCant) spanCant.textContent = r.data.cantidad;

            // Actualizar los totales de la tarjeta (Camas y Personas)
            const elPlazas = document.getElementById(`total_plazas_${idHab}`);
            const elCamas = document.getElementById(`total_camas_${idHab}`);
            
            if (elPlazas) elPlazas.textContent = r.data.total_plazas;
            if (elCamas) elCamas.textContent = r.data.total_camas;
        }

        // --- MANEJO DE ELIMINAR HABITACIÓN ---
        if (e.target.classList.contains("btn-eliminar") || e.target.parentElement.classList.contains("btn-eliminar")) {
            // Detectar el ID si se hizo clic en el icono o en el botón
            const btn = e.target.classList.contains("btn-eliminar") ? e.target : e.target.parentElement;
            const idHab = btn.dataset.id;

            if (!confirm("¿Estás seguro de que deseas eliminar esta habitación? Esta acción no se puede deshacer.")) return;
            
            const r = await postAccion({ 
                accion: "eliminar", 
                id_habitacion: idHab 
            });

            if (r.ok) {
                mostrarToast("Habitación eliminada correctamente.");
                setTimeout(() => location.reload(), 1000);
            } else {
                mostrarToast("Error al eliminar: " + r.mensaje);
            }
        }
    });
})();