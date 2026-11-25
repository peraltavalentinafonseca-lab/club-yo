const lista = document.getElementById("listaAsociados");
const modalElement = document.getElementById("modalAsociado");
const modal = new bootstrap.Modal(modalElement);
const formulario = document.getElementById("formAsociado");

async function api(metodo, datos = null) {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" }
    };
    if (datos) opciones.body = JSON.stringify(datos);

    const respuesta = await fetch("../Modelos/gestionarAsociados.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
        return;
    }
    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || resultado.mensaje || "Error en la solicitud");
    }
    return resultado;
}

async function cargarAsociados() {
    try {
        const resultado = await api("GET");
        mostrarAsociados(resultado.asociados || []);
    } catch (error) {
        alert(error.message);
    }
}

function mostrarAsociados(asociados) {
    lista.replaceChildren();

    if (asociados.length === 0) {
        lista.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted">No hay asociados registrados.</td></tr>';
        return;
    }

    asociados.forEach(asociado => {
        const fila = document.createElement("tr");
        [
            asociado.documento,
            asociado.nombres,
            asociado.apellidos,
            asociado.fechaNacimiento || "",
            asociado.genero,
            asociado.direccionResidencia,
            asociado.ciudad,
            asociado.telefono,
            asociado.email,
            asociado.rol
        ].forEach(valor => {
            const celda = document.createElement("td");
            celda.textContent = valor || "";
            fila.appendChild(celda);
        });

        const acciones = document.createElement("td");
        acciones.className = "text-nowrap";

        const editar = document.createElement("button");
        editar.type = "button";
        editar.className = "btn btn-sm btn-outline-primary me-2";
        editar.innerHTML = '<i class="bi bi-pencil-square"></i>';
        editar.addEventListener("click", () => abrirEdicion(asociado));

        const eliminar = document.createElement("button");
        eliminar.type = "button";
        eliminar.className = "btn btn-sm btn-outline-danger";
        eliminar.innerHTML = '<i class="bi bi-trash-fill"></i>';
        eliminar.addEventListener("click", () => eliminarAsociado(asociado));

        acciones.append(editar, eliminar);
        fila.appendChild(acciones);
        lista.appendChild(fila);
    });
}

function abrirEdicion(asociado) {
    document.getElementById("idAsociado").value = asociado.idAsociado;
    document.getElementById("documento").value = asociado.documento || "";
    document.getElementById("nombres").value = asociado.nombres || "";
    document.getElementById("apellidos").value = asociado.apellidos || "";
    document.getElementById("fechaNacimiento").value = asociado.fechaNacimiento || "";
    document.getElementById("genero").value = asociado.genero || "";
    document.getElementById("direccionResidencia").value = asociado.direccionResidencia || "";
    document.getElementById("ciudad").value = asociado.ciudad || "";
    document.getElementById("telefono").value = asociado.telefono || "";
    document.getElementById("email").value = asociado.email || "";
    modal.show();
}

async function eliminarAsociado(asociado) {
    if (!confirm(`¿Desea eliminar al asociado ${asociado.nombres} ${asociado.apellidos}?`)) return;

    try {
        await api("DELETE", { idAsociado: asociado.idAsociado });
        await cargarAsociados();
        alert("Asociado eliminado correctamente.");
    } catch (error) {
        alert(error.message);
    }
}

formulario.addEventListener("submit", async evento => {
    evento.preventDefault();

    const datos = {
        idAsociado: Number(document.getElementById("idAsociado").value),
        documento: document.getElementById("documento").value.trim(),
        nombres: document.getElementById("nombres").value.trim(),
        apellidos: document.getElementById("apellidos").value.trim(),
        fechaNacimiento: document.getElementById("fechaNacimiento").value,
        genero: document.getElementById("genero").value,
        direccionResidencia: document.getElementById("direccionResidencia").value.trim(),
        ciudad: document.getElementById("ciudad").value.trim(),
        telefono: document.getElementById("telefono").value.trim(),
        email: document.getElementById("email").value.trim()
    };

    try {
        await api("PUT", datos);
        modal.hide();
        await cargarAsociados();
        alert("Asociado actualizado correctamente.");
    } catch (error) {
        alert(error.message);
    }
});

cargarAsociados();
