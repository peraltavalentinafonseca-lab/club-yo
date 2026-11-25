const URL_PRESTAMOS = "../Modelos/gestionarPrestamos.php";

const formPrestamo = document.getElementById("formPrestamo");
const selectAsociado = document.getElementById("idAsociado");
const inputIdPrestamo = document.getElementById("idPrestamo");
const inputValor = document.getElementById("valorPrestamo");
const inputTasa = document.getElementById("tasaInteres");
const inputCuotas = document.getElementById("numeroCuotas");
const inputFecha = document.getElementById("fechaPrestamo");
const btnCancelar = document.getElementById("btnCancelarEdicion");
const listaPrestamos = document.getElementById("listaPrestamos");
const mensajePrestamo = document.getElementById("mensajePrestamo");

const monedaCOP = valor => Number(valor || 0).toLocaleString("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0
});

function mostrarAlerta(texto, tipo = "info") {
    if (!mensajePrestamo) return;
    mensajePrestamo.innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${texto}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>`;
}

function ponerFechaHoy() {
    if (inputFecha && !inputFecha.value) {
        inputFecha.value = new Date().toISOString().slice(0, 10);
    }
}

function resetearFormulario() {
    formPrestamo.reset();
    inputIdPrestamo.value = "";
    inputFecha.value = new Date().toISOString().slice(0, 10);
    btnCancelar.classList.add("d-none");
}

async function peticionPrestamos(metodo, datos = null) {
    const opciones = {
        method: metodo,
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" }
    };
    if (datos) opciones.body = JSON.stringify(datos);

    const respuesta = await fetch(URL_PRESTAMOS, opciones);
    const texto = await respuesta.text();

    let data;
    try {
        data = JSON.parse(texto);
    } catch {
        throw new Error(`Respuesta no válida del servidor (${respuesta.status})`);
    }

    if (respuesta.status === 401) {
        window.location.href = "login.html";
        return null;
    }

    if (!respuesta.ok || !data.success) {
        throw new Error(data.message || "Error al procesar la solicitud de préstamos");
    }

    return data;
}

function renderizarAsociados(asociados) {
    selectAsociado.replaceChildren(new Option("Seleccione un asociado", ""));

    asociados.forEach(a => {
        const nombreCompleto = `${a.nombres ?? a.nombre ?? ""} ${a.apellidos ?? a.apellido ?? ""}`.trim();
        const doc = a.documento ? ` (${a.documento})` : "";
        const etiqueta = nombreCompleto ? `${nombreCompleto}${doc}` : `Asociado #${a.idAsociado}${doc}`;
        selectAsociado.add(new Option(etiqueta, a.idAsociado));
    });
}

function renderizarPrestamos(prestamos) {
    listaPrestamos.replaceChildren();

    if (!prestamos.length) {
        listaPrestamos.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-3 text-muted">No hay préstamos registrados.</td>
            </tr>`;
        return;
    }

    prestamos.forEach(p => {
        const nombreAsociado = p.nombreAsociado || `${p.nombres ?? ""} ${p.apellidos ?? ""}`.trim() || `Asociado #${p.idAsociado}`;
        const tr = document.createElement("tr");

        tr.innerHTML = `
            <td>#${p.idPrestamo}</td>
            <td>${nombreAsociado}</td>
            <td>${monedaCOP(p.valorPrestamo)}</td>
            <td>${p.tasaInteres}%</td>
            <td>${p.numeroCuotas}</td>
            <td>${p.fechaPrestamo}</td>
            <td>
                <button type="button" class="btn btn-sm btn-warning btn-editar" data-id="${p.idPrestamo}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger btn-eliminar" data-id="${p.idPrestamo}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>`;

        tr.querySelector(".btn-editar").addEventListener("click", () => cargarEnFormulario(p));
        tr.querySelector(".btn-eliminar").addEventListener("click", () => eliminarPrestamo(p.idPrestamo));

        listaPrestamos.appendChild(tr);
    });
}

function cargarEnFormulario(prestamo) {
    inputIdPrestamo.value = prestamo.idPrestamo;
    selectAsociado.value = prestamo.idAsociado;
    inputValor.value = prestamo.valorPrestamo;
    inputTasa.value = prestamo.tasaInteres;
    inputCuotas.value = prestamo.numeroCuotas;
    inputFecha.value = prestamo.fechaPrestamo;
    btnCancelar.classList.remove("d-none");
    window.scrollTo({ top: 0, behavior: "smooth" });
}

async function eliminarPrestamo(idPrestamo) {
    if (!confirm(`¿Deseas eliminar el préstamo #${idPrestamo}?`)) return;

    try {
        const data = await peticionPrestamos("DELETE", { idPrestamo });
        if (!data) return;
        mostrarAlerta(data.message || "Préstamo eliminado correctamente.", "success");
        await cargarDatosPrestamos();
    } catch (error) {
        mostrarAlerta(error.message, "danger");
    }
}

async function cargarDatosPrestamos() {
    try {
        const data = await peticionPrestamos("GET");
        if (!data) return;

        renderizarAsociados(data.asociados || []);
        renderizarPrestamos(data.prestamos || []);
    } catch (error) {
        listaPrestamos.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-3 text-danger">${error.message}</td>
            </tr>`;
        mostrarAlerta(error.message, "danger");
    }
}

formPrestamo.addEventListener("submit", async e => {
    e.preventDefault();

    const idPrestamo = inputIdPrestamo.value ? Number(inputIdPrestamo.value) : null;
    const payload = {
        idAsociado: Number(selectAsociado.value),
        valorPrestamo: Number(inputValor.value),
        tasaInteres: Number(inputTasa.value),
        numeroCuotas: Number(inputCuotas.value),
        fechaPrestamo: inputFecha.value
    };

    if (idPrestamo) {
        payload.idPrestamo = idPrestamo;
    }

    try {
        const metodo = idPrestamo ? "PUT" : "POST";
        const data = await peticionPrestamos(metodo, payload);
        if (!data) return;

        mostrarAlerta(data.message || "Préstamo guardado correctamente.", "success");
        resetearFormulario();
        await cargarDatosPrestamos();
    } catch (error) {
        mostrarAlerta(error.message, "danger");
    }
});

btnCancelar.addEventListener("click", resetearFormulario);

ponerFechaHoy();
cargarDatosPrestamos();