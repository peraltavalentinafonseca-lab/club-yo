const formulario = document.getElementById("FormularioRegistro") || document.getElementById("formAsociados");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const Asociado = Object.fromEntries(new FormData(formulario).entries());
        const mensajeEl = document.getElementById("mensajeRegistro");

        const pass = Asociado.contrasena || Asociado.contraseña || Asociado.password || Asociado.clave || "";
        const confirmPass = Asociado.confirmarContrasena || Asociado.confirmarContraseña || Asociado.confirmPassword;

        // 1. Validar longitud mínima de 8 caracteres (sin permitir subir el formulario si es menor)
        if (!pass || pass.length < 8) {
            if (mensajeEl) {
                mensajeEl.textContent = "La contraseña debe tener mínimo 8 caracteres.";
                mensajeEl.style.color = "red";
                mensajeEl.style.fontWeight = "bold";
            } else {
                alert("La contraseña debe tener mínimo 8 caracteres.");
            }
            return; // Detiene el envío
        }

        // 2. Comparar contraseñas si existe campo de confirmación
        if (confirmPass !== undefined && confirmPass !== "" && pass !== confirmPass) {
            if (mensajeEl) {
                mensajeEl.textContent = "Las contraseñas no coinciden.";
                mensajeEl.style.color = "red";
                mensajeEl.style.fontWeight = "bold";
            } else {
                alert("Las contraseñas no coinciden.");
            }
            return; // Detiene el envío
        }

        // Limpiar campos de confirmación y asegurar contrasena sin ñ
        delete Asociado.confirmarContraseña;
        delete Asociado.confirmarContrasena;
        delete Asociado.confirmPassword;
        delete Asociado.contraseña;
        Asociado.contrasena = pass;

        if (botonGuardar) botonGuardar.disabled = true;

        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.style.color = "#333";
            mensajeEl.style.fontWeight = "bold";
        }

        try {
            const urlDestino = formulario.action || '../Modelo/guardarAsociado.php';
            const respuesta = await fetch(urlDestino, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Asociado)
            });

            let resultado;
            try {
                resultado = await respuesta.json();
            } catch {
                throw new Error(`El servidor respondió con HTTP ${respuesta.status}, pero no devolvió JSON.`);
            }

            if (mensajeEl) {
                mensajeEl.textContent = resultado.message;
                mensajeEl.style.color = resultado.success ? "green" : "red";
                mensajeEl.style.fontWeight = "bold";
                mensajeEl.style.marginTop = "10px";
            } else {
                alert(resultado.message);
            }

            if (respuesta.ok && resultado.success) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario: ", error);
            if (mensajeEl) {
                mensajeEl.textContent = error.message || "Ocurrió un error al enviar el formulario.";
                mensajeEl.style.color = "red";
            } else {
                alert(error.message || "Ocurrió un error al enviar el formulario.");
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}