const formulario = document.getElementById("FormRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const mensajeEl = document.getElementById("mensajeRegistro");
        const asociado = Object.fromEntries(new FormData(formulario).entries());

        if (asociado.contraseña !== asociado.confirmarContraseña) {
            if (mensajeEl) {
                mensajeEl.textContent = "Las contraseñas no coinciden.";
            }
            return;
        }

        delete asociado.confirmarContraseña;

        if (botonGuardar) botonGuardar.disabled = true;

        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.dataset.tipo = "";
        }

        try {
            const respuesta = await fetch(formulario.action, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(asociado)
            });

            const resultado = await respuesta.json();

            if (mensajeEl) {
                mensajeEl.dataset.tipo = respuesta.ok && resultado.success ? "ok" : "error";
                mensajeEl.textContent = resultado.mensaje || resultado.message || "Proceso completado.";
            }

            if (respuesta.ok && resultado.success) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            if (mensajeEl) {
                mensajeEl.dataset.tipo = "error";
                mensajeEl.textContent = "Ocurrió un error. Por favor, intentalo de nuevo.";
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}