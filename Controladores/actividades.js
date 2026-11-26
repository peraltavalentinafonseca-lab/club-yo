const listaActividades = document.getElementById("listaActividades");
const mensajeActividad = document.getElementById("mensajeActividad");
let esAdministrador = false;
let actividades = [];
let actividadesInscritas = new Set();
let accionesConfiguradas = false;
const formularioActividad = document.getElementById("formActividad");
const modalActividad = new bootstrap.Modal(document.getElementById("modalActividad"));

const URL_ACTIVIDADES = "../Modelos/gestionarActividades.php";
const URL_INSCRIPCION = "../Modelos/inscribirActividad.php";

function mostrarAlerta(texto, tipo = "info") {
    if (!mensajeActividad) {
        alert(texto);
        return;
    }

    mensajeActividad.innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show rounded-3 mb-0" role="alert">
            ${texto}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    `;
}

async function solicitarEndpoint(url, metodo, datos) {
    const opciones = {
        method: metodo,
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin"
    };

    if (metodo !== "GET" && datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch(url, opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
        return null;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || resultado.mensaje || "Error en la solicitud");
    }

    return resultado;
}

async function solicitarApi(metodo, datos) {
    return solicitarEndpoint(URL_ACTIVIDADES, metodo, datos);
}

async function cargarActividades() {
    try {
        const [resultadoActividades, resultadoInscripciones] = await Promise.all([
            solicitarApi("GET"),
            solicitarEndpoint(URL_INSCRIPCION, "GET").catch(() => ({ inscritas: [] }))
        ]);

        if (!resultadoActividades) return;

        const rol = String(resultadoActividades.rol || "").toLowerCase();
        esAdministrador = rol === "admin" || rol === "administrador";
        actividadesInscritas = new Set(
            Array.isArray(resultadoInscripciones?.inscritas)
                ? resultadoInscripciones.inscritas.map(id => Number(id))
                : []
        );

        configurarAcciones();

        actividades = resultadoActividades.actividades || [];
        mostrarActividades();
    } catch (error) {
        alert("Error al cargar las actividades: " + error.message);
    }
}

function mostrarActividades() {
    listaActividades.replaceChildren();
    const columnasBase = 5; // Actividad, Fecha, Municipio, Total Recaudado, Inscripción
    const totalColumnas = esAdministrador ? columnasBase + 1 : columnasBase;

    if (actividades.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = totalColumnas;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay actividades disponibles.";
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement("tr");
        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento),
            crearCelda(actividad.municipio),
            crearCelda(
                actividad.totalRecaudado === null
                    ? "Pendiente"
                    : Number(actividad.totalRecaudado).toLocaleString("es-CO", { style: "currency", currency: "COP" })
            ),
            crearCeldaInscripcion(actividad)
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.title = "Editar actividad";
            botonEditar.innerHTML = '<i class="bi bi-pencil-square"></i>';
            botonEditar.addEventListener("click", () => abrirEdicion(actividad));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.title = "Eliminar actividad";
            botonEliminar.innerHTML = '<i class="bi bi-trash-fill"></i>';
            botonEliminar.addEventListener("click", () => eliminarActividad(actividad));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }
        listaActividades.append(fila);
    });
}

function crearCeldaInscripcion(actividad) {
    const celda = document.createElement("td");
    celda.className = "text-nowrap";
    const idAct = Number(actividad.idActividad);

    if (actividadesInscritas.has(idAct)) {
        const badge = document.createElement("span");
        badge.className = "badge-inscrito";
        badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Inscrito';
        celda.append(badge);
        return celda;
    }

    const botonInscribir = document.createElement("button");
    botonInscribir.type = "button";
    botonInscribir.className = "btn btn-sm btn-morado";
    botonInscribir.innerHTML = '<i class="bi bi-person-plus me-1"></i>Inscribirme';
    botonInscribir.addEventListener("click", () => inscribirseActividad(actividad, botonInscribir));

    celda.append(botonInscribir);
    return celda;
}

async function inscribirseActividad(actividad, boton) {
    boton.disabled = true;
    const textoOriginal = boton.innerHTML;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Inscribiendo...';

    try {
        const resultado = await solicitarEndpoint(URL_INSCRIPCION, "POST", {
            idActividad: Number(actividad.idActividad)
        });
        if (!resultado) return;

        mostrarAlerta(resultado.message || `Te has inscrito en "${actividad.nombreActividad}".`, "success");
        await cargarActividades();
    } catch (error) {
        boton.disabled = false;
        boton.innerHTML = textoOriginal;
        mostrarAlerta(error.message, "danger");
    }
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

async function eliminarActividad(actividad) {
    const confirmar = window.confirm(`¿Desea eliminar la actividad "${actividad.nombreActividad}"?`);

    if (!confirmar) {
        return;
    }

    try {
        await solicitarApi("DELETE", { idActividad: actividad.idActividad });
        await cargarActividades();
        alert("Actividad eliminada correctamente.");
    } catch (error) {
        alert(error.message);
    }
}

function configurarAcciones() {
    if (accionesConfiguradas) {
        return;
    }
    const botonNueva = document.getElementById("nuevaActividad");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if (esAdministrador) {
        botonNueva.classList.remove("d-none");
        botonNueva.addEventListener("click", abrirNuevaActividad);

        encabezadoAcciones.classList.remove("d-none");
        accionesConfiguradas = true;
    }
}

function abrirNuevaActividad() {
    formularioActividad.reset();
    document.getElementById("campoTotalRecaudado").classList.add("d-none");
    document.getElementById("tituloModalActividad").textContent = "Nueva Actividad";
    document.getElementById("guardarActividad").textContent = "Guardar Actividad";
    modalActividad.show();
}

function abrirEdicion(actividad) {
    formularioActividad.reset();
    document.getElementById("idActividad").value = actividad.idActividad;
    document.getElementById("nombreActividad").value = actividad.nombreActividad;
    document.getElementById("fechaEvento").value = actividad.fechaEvento;
    document.getElementById("municipio").value = actividad.municipio;
    document.getElementById("totalRecaudado").value = actividad.totalRecaudado ?? "";

    document.getElementById("campoTotalRecaudado").classList.remove("d-none");
    document.getElementById("tituloModalActividad").textContent = "Guardar Cambios";
    modalActividad.show();
}

formularioActividad.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const idActividad = document.getElementById("idActividad").value;

    const datos = {
        nombreActividad: document.getElementById("nombreActividad").value.trim(),
        fechaEvento: document.getElementById("fechaEvento").value,
        municipio: document.getElementById("municipio").value.trim()
    };

    if (idActividad) {
        datos.idActividad = Number(idActividad);
        const total = document.getElementById("totalRecaudado").value;
        datos.totalRecaudado = total === "" ? null : Number(total);
    }

    try {
        await solicitarApi(idActividad ? "PUT" : "POST", datos);
        modalActividad.hide();
        alert("Actividad guardada correctamente");
        cargarActividades();
    } catch (error) {
        alert(error.message);
    }
});

cargarActividades();