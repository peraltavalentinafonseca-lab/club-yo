const URL_PERFIL = "../Modelos/perfilAsociado.php";

document.addEventListener("DOMContentLoaded", async () => {
    const form = document.getElementById("formMisDatos");
    const mensaje = document.getElementById("mensajeMisDatos");

    if (!form) return;

    try {
        if (mensaje) {
            mensaje.textContent = "Cargando información...";
            mensaje.style.color = "#333";
        }

        const resp = await fetch(URL_PERFIL, { method: "GET" });
        const data = await resp.json();

        if (!resp.ok || !data.success || !data.asociado) {
            if (resp.status === 401) {
                window.location.href = "login.html";
                return;
            }
            throw new Error(data.mensaje || data.message || "No se pudieron cargar los datos.");
        }

        const a = data.asociado;
        document.getElementById("documento").value = a.documento || "";
        document.getElementById("nombres").value = a.nombres || "";
        document.getElementById("apellidos").value = a.apellidos || "";
        document.getElementById("fechaNacimiento").value = a.fechaNacimiento || "";
        document.getElementById("genero").value = a.genero || "";
        document.getElementById("direccionResidencia").value = a.direccionResidencia || "";
        document.getElementById("ciudad").value = a.ciudad || "";
        document.getElementById("telefono").value = a.telefono || "";
        document.getElementById("email").value = a.email || "";

        if (mensaje) {
            mensaje.textContent = "";
        }
    } catch (error) {
        console.error("Error al cargar perfil:", error);
        if (mensaje) {
            mensaje.textContent = error.message || "Error al cargar tus datos.";
            mensaje.style.color = "red";
        }
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const boton = form.querySelector('button[type="submit"]');
        const payload = {
            nombres: document.getElementById("nombres").value.trim(),
            apellidos: document.getElementById("apellidos").value.trim(),
            fechaNacimiento: document.getElementById("fechaNacimiento").value,
            genero: document.getElementById("genero").value,
            direccionResidencia: document.getElementById("direccionResidencia").value.trim(),
            ciudad: document.getElementById("ciudad").value.trim(),
            telefono: document.getElementById("telefono").value.trim(),
            email: document.getElementById("email").value.trim()
        };

        if (boton) boton.disabled = true;
        if (mensaje) {
            mensaje.textContent = "Guardando cambios...";
            mensaje.style.color = "#333";
        }

        try {
            const resp = await fetch(URL_PERFIL, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const resultado = await resp.json();

            if (!resp.ok || !resultado.success) {
                throw new Error(resultado.mensaje || resultado.message || "No se pudieron guardar los cambios.");
            }

            if (resultado.nombre) {
                localStorage.setItem("nombre", resultado.nombre);
            }

            if (mensaje) {
                mensaje.textContent = resultado.mensaje || "Datos actualizados correctamente.";
                mensaje.style.color = "green";
            }
        } catch (error) {
            console.error("Error al actualizar perfil:", error);
            if (mensaje) {
                mensaje.textContent = error.message || "Ocurrió un error al guardar.";
                mensaje.style.color = "red";
            }
        } finally {
            if (boton) boton.disabled = false;
        }
    });
});