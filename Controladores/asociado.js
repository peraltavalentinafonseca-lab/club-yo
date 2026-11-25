const formulario = document.getElementById("FormRegistro");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const mensajeEl = document.getElementById("MensajeRegistro");
        const asociado = Object.fromEntries(new FormData(formulario).entries());

        if (asociado.contraseña !== asociado.confirmarContraseña) {
            if (mensajeEl) {
                mensajeEl.dataset.tipo = "error";
                mensajeEl.textContent = "Las contraseñas no coinciden.";
            }
            return;
        }

        delete asociado.confirmarContraseña;

        if (botonGuardar) botonGuardar.disabled = true;

        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.dataset.tipo = "cargando";
        }

        try {
            const urlDestino = formulario.getAttribute("action") || formulario.action;
            const respuesta = await fetch(urlDestino, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(asociado)
            });

            const resultado = await respuesta.json();
            const esExito = respuesta.ok && Boolean(resultado.success);

            if (mensajeEl) {
                mensajeEl.dataset.tipo = esExito ? "ok" : "error";
                mensajeEl.textContent =
                    resultado.mensaje ||
                    resultado.message ||
                    (esExito ? "Registro guardado correctamente." : "No se pudo completar el registro.");
            }

            if (esExito) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            if (mensajeEl) {
                mensajeEl.dataset.tipo = "error";
                mensajeEl.textContent = "Ocurrió un error. Por favor, inténtalo de nuevo.";
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}