const formulario = document.getElementById("FormularioRegistro")

formulario.addEventListener("submit", async function (event) {
    //evita recargar la pagina al enviar el formulario
    event.preventDefault();

    const botonGuardar = formulario.querySelector('button[type="submit"]');

    //los datos del formulario los convierte en objetos
    const asocido = Object.fromEntries(new FormData(formulario).entries())

    if (asociado.contraseña !== asociado.confirmarContraseña) {
        document.getElementById("mensajeRegistro").textContent =
         "Las contraseñas no coinciden.";
    }
    delete asociado.confirmarContraseña;

    alert("Datos del formulario: " + JSON.stringify(asocido));

    botonGuardar.disabled = true;//deshabilita el boton mientras se termina el proceso

    try {
        const respuesta = await fetch(formulario.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(asocido) });

        //pasar la respuesta JSNO a un objeto de javascript
        const resultado = await respuesta.json();
        document.getElementById("mensajeRegistro").dataset.tipo = '';//limpia cualquier mensaje anterior

        document.getElementById("mensajeRegistro").textContent = resultado.mensaje;

        if (respuesta.ok && resultado.success) {
            formulario.reset();//limpia el formulario si la solicitud fue exitosa
        }

    } catch (error) {
        console.error("Error al enviar el formulario", error)
        document.getElementById("mensajeRegistro").textContent= "Ocurrió un error. Por favor, intentalo de nuevo."
    } finally {
        botonGuardar.disabled = false;//Habilita el boton nuevamente
    }
}
)