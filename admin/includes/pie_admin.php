</div> <!-- /container -->

<footer class="bg-dark text-white text-center py-3 mt-5">
    <div class="container">
        <small>© <?php echo date("Y"); ?> HSAMP - Panel de Administración</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEx = new bootstrap.Modal(document.getElementById('modalExtra'));

    // --- ACTUALIZAR HABITACIÓN ---
    document.querySelectorAll('.btn-save-h').forEach(btn => {
        btn.onclick = async function() {
            const id = this.dataset.id;
            const precio = document.getElementById('h_' + id).value;
            
            const fd = new FormData();
            fd.append('accion', 'actualizar_precio_habitacion');
            fd.append('id', id);
            fd.append('precio', precio);

            try {
                const res = await fetch('precios.php?ajax=1', { method: 'POST', body: fd });
                const r = await res.json();
                if(r.ok) {
                    // Feedback visual sin refrescar
                    this.innerHTML = '¡Guardado!';
                    this.className = 'btn btn-success btn-sm';
                    setTimeout(() => {
                        this.innerHTML = 'Actualizar';
                        this.className = 'btn btn-primary btn-sm';
                    }, 2000);
                }
            } catch (e) { alert("Error al guardar"); }
        };
    });

    // --- GESTIÓN EXTRAS ---
    window.nuevoExtra = () => {
        document.getElementById("formExtra").reset();
        document.getElementById("ex_id").value = "";
        modalEx.show();
    };

    document.querySelectorAll('.btn-edit-extra').forEach(btn => {
        btn.onclick = function() {
            const d = JSON.parse(this.dataset.json);
            document.getElementById("ex_id").value = d.id_extra;
            document.getElementById("ex_nom").value = d.nombre;
            document.getElementById("ex_pre").value = d.precio;
            modalEx.show();
        };
    });

    document.getElementById("formExtra").onsubmit = async function(e) {
        e.preventDefault();
        const res = await fetch('precios.php?ajax=1', { method: 'POST', body: new FormData(this) });
        const r = await res.json();
        if(r.ok) {
            alert(r.mensaje);
            location.reload(); // En extras sí recargamos para ver el nuevo en la lista
        }
    };
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/habitaciones_accion.js"></script>

