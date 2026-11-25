let datosReporte = {
    asociados: [],
    prestamos: [],
    actividades: []
};

document.addEventListener('DOMContentLoaded', () => {
    cargarReportes();

    const buscador = document.getElementById('buscarReporte');
    if (buscador) {
        buscador.addEventListener('input', aplicarFiltro);
    }
});

function formatoMoneda(valor) {
    return '$' + Number(valor || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

async function cargarReportes() {
    const alertaEl = document.getElementById('alertaReporte');

    try {
        const res = await fetch('../Modelos/reportes.php', {
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            if (alertaEl) {
                alertaEl.innerHTML = `<div class="alert alert-danger">${data.message || 'No fue posible cargar los reportes.'}</div>`;
            }
            document.getElementById('tablaEstadoAsociados').innerHTML =
                '<tr><td colspan="7" class="text-center text-danger py-3">Sin acceso o error al consultar</td></tr>';
            document.getElementById('tablaCarteraPrestamos').innerHTML =
                '<tr><td colspan="9" class="text-center text-danger py-3">Sin acceso o error al consultar</td></tr>';
            document.getElementById('tablaBalanceActividades').innerHTML =
                '<tr><td colspan="9" class="text-center text-danger py-3">Sin acceso o error al consultar</td></tr>';
            return;
        }

        const resumen = data.resumen || {};
        document.getElementById('kpiAsociados').textContent = resumen.totalAsociados ?? 0;
        document.getElementById('kpiPrestado').textContent = formatoMoneda(resumen.totalPrestado);
        document.getElementById('kpiPagado').textContent = formatoMoneda(resumen.totalPagado);
        document.getElementById('kpiSaldo').textContent = formatoMoneda(resumen.saldoPendiente);
        document.getElementById('kpiActividades').textContent = formatoMoneda(resumen.totalRecaudadoActividades);

        datosReporte.asociados = data.asociados || [];
        datosReporte.prestamos = data.prestamos || [];
        datosReporte.actividades = data.actividades || [];

        renderizarAsociados(datosReporte.asociados);
        renderizarPrestamos(datosReporte.prestamos);
        renderizarActividades(datosReporte.actividades);
    } catch (error) {
        console.error('Error al cargar reportes:', error);
        if (alertaEl) {
            alertaEl.innerHTML = '<div class="alert alert-danger">Error de conexión al cargar los reportes.</div>';
        }
    }
}

function renderizarAsociados(lista) {
    const tbody = document.getElementById('tablaEstadoAsociados');
    if (!tbody) return;

    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3">No hay registros para mostrar.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(item => {
        const prestado = Number(item.totalPrestado || 0);
        const abonado = Number(item.totalAbonado || 0);
        const saldo = Math.max(0, prestado - abonado);

        return `
            <tr>
                <td>${item.documento}</td>
                <td>${item.asociado}</td>
                <td>${item.prestamosActivos}</td>
                <td>${item.actividadesParticipadas ?? 0}</td>
                <td>${formatoMoneda(prestado)}</td>
                <td>${formatoMoneda(abonado)}</td>
                <td class="fw-semibold">${formatoMoneda(saldo)}</td>
            </tr>
        `;
    }).join('');
}

function renderizarPrestamos(lista) {
    const tbody = document.getElementById('tablaCarteraPrestamos');
    if (!tbody) return;

    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3">No hay préstamos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(p => {
        const valor = Number(p.valorPrestamo || 0);
        const pagado = Number(p.totalPagado || 0);
        const saldo = Math.max(0, valor - pagado);

        return `
            <tr>
                <td>${p.idPrestamo}</td>
                <td>${p.asociado}</td>
                <td>${p.documento}</td>
                <td>${p.fechaPrestamo}</td>
                <td>${formatoMoneda(valor)}</td>
                <td>${p.tasaInteres}%</td>
                <td>${p.numeroCuotas}</td>
                <td>${formatoMoneda(pagado)}</td>
                <td class="fw-semibold">${formatoMoneda(saldo)}</td>
            </tr>
        `;
    }).join('');
}

function renderizarActividades(lista) {
    const tbody = document.getElementById('tablaBalanceActividades');
    if (!tbody) return;

    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3">No hay actividades registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(act => {
        const total = Number(act.totalRecaudado || 0);
        const participantes = parseInt(act.participantes, 10) || 0;
        const mitadClub = total * 0.5;
        const mitadParticipantes = total * 0.5;
        const porParticipante = participantes > 0 ? mitadParticipantes / participantes : 0;

        return `
            <tr>
                <td>${act.idActividad}</td>
                <td>${act.nombreActividad}</td>
                <td>${act.fechaEvento}</td>
                <td>${act.municipio}</td>
                <td>${participantes}</td>
                <td>${formatoMoneda(total)}</td>
                <td>${formatoMoneda(mitadClub)}</td>
                <td>${formatoMoneda(mitadParticipantes)}</td>
                <td class="fw-semibold">${formatoMoneda(porParticipante)}</td>
            </tr>
        `;
    }).join('');
}

function aplicarFiltro() {
    const termino = (document.getElementById('buscarReporte')?.value || '').trim().toLowerCase();
    if (!termino) {
        renderizarAsociados(datosReporte.asociados);
        renderizarPrestamos(datosReporte.prestamos);
        renderizarActividades(datosReporte.actividades);
        return;
    }

    const asociadosFiltrados = datosReporte.asociados.filter(a =>
        String(a.asociado || '').toLowerCase().includes(termino) ||
        String(a.documento || '').toLowerCase().includes(termino)
    );

    const prestamosFiltrados = datosReporte.prestamos.filter(p =>
        String(p.asociado || '').toLowerCase().includes(termino) ||
        String(p.documento || '').toLowerCase().includes(termino)
    );

    const actividadesFiltradas = datosReporte.actividades.filter(act =>
        String(act.nombreActividad || '').toLowerCase().includes(termino) ||
        String(act.municipio || '').toLowerCase().includes(termino)
    );

    renderizarAsociados(asociadosFiltrados);
    renderizarPrestamos(prestamosFiltrados);
    renderizarActividades(actividadesFiltradas);
}