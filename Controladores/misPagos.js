const lista = document.getElementById("listaPagos");
const form = document.getElementById("formPago");
const selectPrestamo = document.getElementById("prestamo");
const mensaje = document.getElementById("mensajePago");
const inputFecha = document.getElementById("fechaPago");

const URL = "../Modelos/misPagos.php";
const moneda = valor => Number(valor || 0).toLocaleString("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0
});

function ponerFechaHoy() {
    if (inputFecha) {
        inputFecha.value = new Date().toISOString().slice(0, 10);
    }
}

async function solicitar(metodo, datos = null) {
    const opciones = {
        method: metodo,
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" }
    };
    if (datos) opciones.body = JSON.stringify(datos);

    const respuesta = await fetch(URL, opciones);
    const texto = await respuesta.text();

    let resultado;
    try {
        resultado = JSON.parse(texto);
    } catch {
        throw new Error(`Respuesta inválida del servidor (${respuesta.status})`);
    }

    if (respuesta.status === 401) {
        localStorage.clear();
        window.location.href = "login.html";
        return null;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || resultado.mensaje || "Error en la solicitud");
    }

    return resultado;
}

async function cargarPagos() {
    try {
        const resultado = await solicitar("GET");
        if (!resultado) return;

        const esAdmin = Boolean(resultado.esAdmin);
        mostrarPrestamos(resultado.prestamos || [], esAdmin);
        mostrarPagos(resultado.pagos || [], esAdmin);
    } catch (error) {
        lista.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">${error.message}</td></tr>`;
    }
}

function mostrarPrestamos(prestamos, esAdmin = false) {
    if (prestamos.length === 0) {
        selectPrestamo.replaceChildren(new Option(
            esAdmin ? "No hay préstamos registrados" : "No tienes préstamos registrados",
            ""
        ));
        return;
    }

    selectPrestamo.replaceChildren(new Option("Seleccione un préstamo", ""));

    prestamos.forEach(prestamo => {
        const infoAsociado = esAdmin && prestamo.nombres
            ? ` - ${prestamo.nombres} ${prestamo.apellidos || ""} (${prestamo.documento || ""})`
            : "";

        selectPrestamo.add(new Option(
            `Préstamo #${prestamo.idPrestamo}${infoAsociado} - ${moneda(prestamo.valorPrestamo)} - ${prestamo.numeroCuotas} cuotas`,
            prestamo.idPrestamo
        ));
    });
}

function mostrarPagos(pagos, esAdmin = false) {
    lista.replaceChildren();
    let total = 0;

    if (pagos.length === 0) {
        lista.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">${
            esAdmin ? "No hay pagos registrados." : "No tienes pagos registrados."
        }</td></tr>`;
        document.getElementById("totalPagado").textContent = "Total: " + moneda(0);
        return;
    }

    pagos.forEach(pago => {
        total += Number(pago.valorCuota);
        const fila = document.createElement("tr");

        const refPrestamo = esAdmin && pago.nombres
            ? `#${pago.idPrestamo} - ${pago.nombres} ${pago.apellidos || ""}`
            : `#${pago.idPrestamo}`;

        [
            `#${pago.idPago}`,
            refPrestamo,
            moneda(pago.valorCuota),
            pago.fechaPago
        ].forEach(texto => {
            const celda = document.createElement("td");
            celda.textContent = texto;
            fila.appendChild(celda);
        });

        lista.appendChild(fila);
    });

    document.getElementById("totalPagado").textContent = "Total: " + moneda(total);
}

form.addEventListener("submit", async evento => {
    evento.preventDefault();

    const datos = {
        idPrestamo: Number(selectPrestamo.value),
        valorCuota: Number(document.getElementById("valorCuota").value),
        fechaPago: inputFecha.value
    };

    if (!datos.idPrestamo || datos.valorCuota <= 0 || !datos.fechaPago) {
        mensaje.textContent = "Complete todos los datos del pago.";
        mensaje.className = "small fw-bold mb-3 text-danger";
        return;
    }

    try {
        mensaje.textContent = "Guardando pago...";
        mensaje.className = "small fw-bold mb-3 text-secondary";

        const resultado = await solicitar("POST", datos);
        if (!resultado) return;

        form.reset();
        ponerFechaHoy();

        mensaje.textContent = "Pago registrado correctamente.";
        mensaje.className = "small fw-bold mb-3 text-success";
        await cargarPagos();
    } catch (error) {
        mensaje.textContent = error.message;
        mensaje.className = "small fw-bold mb-3 text-danger";
    }
});

ponerFechaHoy();
cargarPagos();