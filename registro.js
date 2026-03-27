document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("formRegistro");
  if (!form) return;

  function mostrarToast(mensaje) {
    const toastEl = document.getElementById("toastErrorRegistro");
    const texto = document.getElementById("toastErrorTexto");
    texto.textContent = mensaje;

    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
  }

  function claveSegura(clave) {
    // min 10, 1 mayus, 1 num, 1 especial
    const patron = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/;
    return patron.test(clave);
  }

  form.addEventListener("submit", function (e) {
    const email = document.getElementById("email").value.trim();
    const telefono = document.getElementById("telefono").value.trim();
    const documento = document.getElementById("documento").value.trim();
    const clave = document.getElementById("clave").value;
    const clave2 = document.getElementById("clave2").value;

    // Email básico
    if (!email.includes("@") || !email.includes(".")) {
      e.preventDefault();
      mostrarToast("El email no parece válido.");
      return;
    }

    // Teléfono: vacío o 9 dígitos (acepta +34)
    if (telefono !== "") {
      let t = telefono.replace(/\s|-/g, "");
      if (t.startsWith("+34")) t = t.substring(3);
      if (!/^\d{9}$/.test(t)) {
        e.preventDefault();
        mostrarToast("El teléfono debe tener 9 dígitos (opcional +34).");
        return;
      }
    }

    // Documento: si hay algo, mínimo formato básico (no hacemos cálculo aquí, eso lo hace PHP)
    if (documento !== "") {
      const doc = documento.toUpperCase().replace(/[\s.-]/g, "");
      if (!/^(\d{8}[A-Z]|[XYZ]\d{7}[A-Z])$/.test(doc)) {
        e.preventDefault();
        mostrarToast("El DNI/NIE no tiene un formato válido.");
        return;
      }
    }

    // Claves
    if (clave !== clave2) {
      e.preventDefault();
      mostrarToast("Las contraseñas no coinciden.");
      return;
    }

    if (!claveSegura(clave)) {
      e.preventDefault();
      mostrarToast("Contraseña inválida: mínimo 10 caracteres, 1 mayúscula, 1 número y 1 carácter especial.");
      return;
    }
  });
});
