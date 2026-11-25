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
        window.location.href = "login.html";
        return;
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

        mostrarPrestamos(resultado.prestamos || []);
        mostrarPagos(resultado.pagos || []);
    } catch (error) {
        lista.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">${error.message}</td></tr>`;
    }
}

function mostrarPrestamos(prestamos) {
    if (prestamos.length === 0) {
        selectPrestamo.replaceChildren(new Option("No tienes préstamos registrados", ""));
        return;
    }

    selectPrestamo.replaceChildren(new Option("Seleccione un préstamo", ""));

    prestamos.forEach(prestamo => {
        selectPrestamo.add(new Option(
            `Préstamo #${prestamo.idPrestamo} - ${moneda(prestamo.valorPrestamo)} - ${prestamo.numeroCuotas} cuotas`,
            prestamo.idPrestamo
        ));
    });
}

function mostrarPagos(pagos) {
    lista.replaceChildren();
    let total = 0;

    if (pagos.length === 0) {
        lista.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No tienes pagos registrados.</td></tr>';
        document.getElementById("totalPagado").textContent = "Total: " + moneda(0);
        return;
    }

    pagos.forEach(pago => {
        total += Number(pago.valorCuota);
        const fila = document.createElement("tr");

        [
            `#${pago.idPago}`,
            `#${pago.idPrestamo}`,
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

        await solicitar("POST", datos);
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